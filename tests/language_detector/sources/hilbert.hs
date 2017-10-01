import Data.List

type Name = String
type Names = [Name]

type Pred = String
type Func = String
type Const = String

-- Figure out how I can create custom type out of an infinite generator for an example
-- Also how to tie Language to Formula, since it's kinda weak now
type Language = (Const, Pred, Func)
data Term = Var Name
          | C Const
          | Fn Func Terms
          deriving (Eq)

instance Show Term where
  show (Var x) = x
  show (C const) = const
  show (Fn f terms) = f ++ "(" ++ (foldr (++) "" (map (show) terms)) ++")"



type Terms = [Term]

data Formula = Termination
             | Atom Pred Terms
             | Not Formula
             | Impl Formula Formula
             | And Formula Formula
             | Or Formula Formula
             | Every Name Formula
             | Exist Name Formula
             | Substitution Formula Name Name
             deriving (Eq)

termVars :: Term -> Names
termVars (Var x) = [x]
termVars (C c) = []
termVars (Fn f terms) = foldr (++) [] $ map (termVars) terms

freeVars :: Formula -> Names
freeVars Termination   = []
freeVars (Atom _ pred)     = foldr (++) [] $ map (termVars) pred
freeVars (Not fi)      = freeVars fi
freeVars (Impl fi psi) = (freeVars fi) ++ (freeVars psi)
freeVars (And fi psi) = (freeVars fi) ++ (freeVars psi)
freeVars (Or fi psi) = (freeVars fi) ++ (freeVars psi)
freeVars (Every x fi)  = (freeVars fi) \\ [x]
freeVars (Exist x fi)  = (freeVars fi) \\ [x]
freeVars fi@(Substitution _ _ _) = freeVars $ sub fi

--
boundVars :: Formula -> Names
boundVars Termination   = []
boundVars (Atom _ pred)       = []
boundVars (Not fi)      = boundVars fi
boundVars (Impl fi psi) = (boundVars fi) ++ (boundVars psi)
boundVars (And fi psi) = (boundVars fi) ++ (boundVars psi)
boundVars (Or fi psi) = (boundVars fi) ++ (boundVars psi)
boundVars (Every x fi)  = (boundVars fi) ++ [x]
boundVars (Exist x fi)  = (boundVars fi) ++ [x]
boundVars fi@(Substitution _ _ _) = boundVars $ sub fi

vars :: Formula -> Names
vars fi = freeVars(fi) ++ boundVars(fi)

sub :: Formula -> Formula
sub (Substitution f x y) = f -- Placeholder

--TODO: explicit Substitution

instance Show Formula where
  show (Termination)  = "⊥"
  show (Atom p terms) = p ++ "(" ++ (foldr (++) "" (map (show) terms)) ++")"
  show (Not fi) = "(" ++ "¬" ++ show fi ++ ")"
  show (Impl fi psi) = "(" ++ show fi ++ "→" ++ show psi ++ ")"
  show (And fi psi) = "(" ++ show fi ++ "∧" ++ show psi ++ ")"
  show (Or fi psi) = "(" ++ show fi ++ "∨" ++ show psi ++ ")"
  show (Exist var fi) = "(" ++ "∃" ++ var ++ show fi ++ ")"
  show (Every var fi) = "(" ++ "∀" ++ var ++ show fi ++ ")"
  show (Substitution fi x y) = show fi ++ "[" ++ x ++ "/" ++ y ++ "]"


isAxiom :: Formula -> Bool
isAxiom f = or (map ($ f) axioms)
--
axioms :: [Formula -> Bool]
axioms = [ isImplAxiom1
         , isImplAxiom2
         , isAndAxiom1
         , isAndAxiom2
         , isOrAxiom1
         , isOrAxiom2
         , isEveryAxiom1
         , isEveryAxiom2
         , isExistAxiom1
         , isExistAxiom2
         , isDNAxiom
         , isContradictionAxiom
         ]

-- Axioms for ->
-- 1. (A → B → C) → (A → B) → A → C
isImplAxiom1 :: Formula -> Bool
isImplAxiom1 (Impl (Impl fi1 (Impl psi1 xi1)) (Impl (Impl fi2 psi2) (Impl fi3 xi2)))
  = fi1 == fi2 && fi2 == fi3 && psi1 == psi2 && xi1 == xi2
isImplAxiom1 _ = False

-- 2. A → B → A
isImplAxiom2 :: Formula -> Bool
isImplAxiom2 (Impl fi (Impl psi xi)) = fi == xi
isImplAxiom2 _ = False

-- -- Axioms for && converted to -> and !
-- -- 3 A ∧ B → A, A ∧ B → B
isAndAxiom1 :: Formula -> Bool
isAndAxiom1 (Impl (And fi psi) xi) = fi == xi || psi == xi
isAndAxiom1 _ = False
--
-- -- 4 A → B → A ∧ B
isAndAxiom2 :: Formula -> Bool
isAndAxiom2 (Impl (Impl fi1 psi1) (And fi2 psi2)) = fi1 == fi2 && psi1 == psi2
isAndAxiom2 _ = False

-- --5 A → A ∨ B, B → A ∨ B
isOrAxiom1 :: Formula -> Bool
isOrAxiom1 (Impl fi (Or psi xi)) = fi == psi || fi == xi
isOrAxiom1 _ = False
--
--6 ((A → C) → (B → C)) → ((A ∨ B) → C)
isOrAxiom2 :: Formula -> Bool
isOrAxiom2 (Impl (Impl (Impl a1 c1) (Impl b1 c2)) (Impl (Or a2 b2) c3)) = a1 == a2 && b1 == b2 && c1 == c2 && c2 == c3
isOrAxiom2 _ = False

-- -- 7 ∀xA → A[x 7→ t]
isEveryAxiom1 :: Formula -> Bool
isEveryAxiom1 (Impl (Every x fi) (Substitution psi y t)) = fi == psi && y == x
isEveryAxiom1 _ = False

-- 8 ∀x (B → A) → (B → ∀xA), ако x ∈/ FV(B)
isEveryAxiom2 :: Formula -> Bool
isEveryAxiom2 (Impl (Every x (Impl b1 a1)) (Impl b2 (Every y a2))) = x == y && b1 == b2 && a1 == a2 && (not $ elem x (freeVars b2))
isEveryAxiom2 _ = False

-- -- 9 A[x 7→ t] → ∃xA
isExistAxiom1 :: Formula -> Bool
isExistAxiom1 (Impl (Substitution fi x t) (Exist y psi)) = x == y && fi == psi
isExistAxiom1 _ = False
--
-- -- 10 ∀x (A → B) → (∃xA → B), ако x ∈/ FV(B)
isExistAxiom2 :: Formula -> Bool
isExistAxiom2 (Impl (Every x (Impl a1 b1)) (Impl (Exist y a2) b2)) = x == y && a1 == a2 && b1 == b2 && (not $ elem x (vars b1))
isExistAxiom2 _ = False
--
-- -- 11 double negation
isDNAxiom :: Formula -> Bool
isDNAxiom (Impl (Not (Not a)) b) = a == b
isDNAxiom _ = False

-- 12 every formula is true for a 
isContradictionAxiom :: Formula -> Bool
isContradictionAxiom (Impl Termination fi) = True
isContradictionAxiom _ = False

type Hypotheses = [Formula]

isDeduction :: Hypotheses -> [Formula] -> Bool
isDeduction hyp fs = isDeduction' hyp [] fs

isTautology :: [Formula] -> Bool
isTautology = isDeduction []

isDeduction' :: Hypotheses -> [Formula] -> [Formula] -> Bool
isDeduction' hyp proved [] = True
isDeduction' hyp proved (f:fs)
  | isAxiom f              = proveNext
  | elem f hyp             = proveNext
  | isModusPonens proved f = proveNext
  | isGen     hyp proved f = proveNext
  | otherwise              = False
  where proveNext = isDeduction' hyp (f : proved) fs

-- Wrong because f doesn't bind to f in comprehension, it's a different f
-- isModusPonens proved f = or [ elem x proved | (Impl x f) <- proved ]

isModusPonens :: [Formula] -> Formula -> Bool
isModusPonens proved f = any (\ (Impl x _) -> elem x proved) impls
  where impls = [impl | impl@(Impl _ fi) <- proved,
                        fi == f]

isGen :: Hypotheses -> [Formula] -> Formula -> Bool
isGen gama proved (Every x fi) = fi `elem` proved && (not $ elem x fvGama)
  where
    fvGama = foldr (++) [] $ map freeVars gama
isGen _ _ _ = False

tau :: Term
tau = Var "x"

x = Atom "p" [tau]
y = Atom ""
target = Impl x x

proof = [
  (Impl x (Impl target x)),
  (Impl (Impl x (Impl target x)) (Impl (Impl x target) (Impl x x))),
  (Impl (Impl x target) (Impl x x)),
  (Impl x (Impl x x)),
  target
  ]

-- A && B |- B && A

a = Atom "A" [tau]
b = Atom "B" [tau]

hyp1 = [
         (And b a)
       ]

test1 = (Impl (Impl a b) a)
proof1 = [ (And b a)
         , (Impl (And b a) a)
         , a -- MP
         , (Impl a (Impl a b)) -- axiom
         , (Impl a b) -- MP
         , (Impl (Impl a b) (And a b))
         , (And a b)
         ]


wrong1 = [ (And b a)
         , (Impl (And b a) a)
         , a -- MP
         , (Impl (Impl a b) a) -- axiom
         , (Impl a b) -- MP
         , (Impl (Impl a b) (And a b))
         , (And a b)
         ]

-- TODO:
--
tau1 :: Term
tau1 = Var "y"

px = Atom "p" [tau]
py = Atom "p" [tau1]

-- goal: (Every y py)
hyp2 = [
        (Every "x" px)
       ]
proof2 =  [ (Every "x" px)
          , (Impl (Every "x" px) (Substitution px "x" "y"))
          , (Substitution px "x" "y")
          , (Every "y" (Substitution px "x" "y"))
          ]

-- goal: (Not a)
--
hyp3 = [
          (Not (Not (Not a)))
       ]

proof3 = [ (Not (Not (Not a)))
         , (Impl (Not (Not (Not a))) (Not a))
         , (Not a)
         ]
