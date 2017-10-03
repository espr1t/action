(<>) :: Int -> Int -> Bool
(<>) = (/=)

ioMax :: Int -> Int -> IO (Maybe Int)
ioMax x y
    |x < y = return (Just y)
    |x == y = do
                return Nothing
                return Nothing
    |otherwise = return (Just x)


main = do
    a <- getLine
    b <- getLine
    max <- ioMax (read a :: Int) (read b :: Int)
    putStrLn (show max)
