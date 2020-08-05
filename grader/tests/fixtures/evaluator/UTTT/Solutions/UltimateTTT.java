import java.awt.*;
import java.util.ArrayList;
import java.util.List;
import java.util.Scanner;
import java.util.Stack;

public class Main {

    private static final int EMPTY = -1;
    private static final int CROSS = 1;
    private static final int NOUGHT = 0;
    private static final int DIMENSION = 3;

    public static void main(String[] args) {
        BigBoard bigBoard = new BigBoard(CROSS);
        Scanner scan = new Scanner(System.in);

        int bigBoardRow = scan.nextInt();
        int bigBoardColumn = scan.nextInt();

        scan.nextLine();

        for (int i = 0; i < 11; i++) {
            String line = scan.nextLine();
            int column = i < 4 ? 0 : i > 7 ? 2 : 1;
            for (int j = 0; j < line.length(); j++) {
                char symbol = line.charAt(j);
                int row = j < 4 ? 0 : j > 7 ? 2 : 1;
                if (symbol == 'X') {
                    bigBoard.getBoard(column, row).draw(i % 4, j % 4, CROSS);
                } else if (symbol == 'O') {
                    bigBoard.getBoard(column, row).draw(i % 4, j % 4, NOUGHT);
                }
            }
        }

        //bigBoard.printNineBoard();
        if (bigBoardRow != -1 && bigBoardColumn != -1) {
            if (!bigBoard.getBoard(bigBoardRow, bigBoardColumn).isOver()) {
                bigBoard.setCurrentBoard(bigBoardRow, bigBoardColumn);
            }
        }
        MinMax ai = new MinMax(bigBoard);
        Point bestMove = ai.getBestMove(bigBoard, CROSS, NOUGHT, 0);
        System.out.println(bigBoard.getCurrentBoardX() + " " + bigBoard.getCurrentBoardY() + " " + bestMove.x+ " " + bestMove.y);
        //bigBoard.printNineBoard();
    }

    public static class Board {


        private int[][] board;
        private int EMPTY = -1;
        private int DIMENSION = 3;
        private Stack<Point> plays;
        private Evaluator scorer;
        private int WON = 100;
        private boolean playmade = false;

        public Board() {
            board = new int[DIMENSION][DIMENSION];
            for (int row = 0; row < DIMENSION; row++) {
                for (int col = 0; col < DIMENSION; col++) {
                    board[row][col] = EMPTY;
                }
            }
            plays = new Stack<Point>();
            scorer = new Evaluator();
        }

        public Board(int[][] state) {
            board = new int[DIMENSION][DIMENSION];
            for (int row = 0; row < DIMENSION; row++) {
                for (int col = 0; col < DIMENSION; col++) {
                    board[row][col] = state[row][col];
                }
            }
            plays = new Stack<Point>();
            scorer = new Evaluator();
        }

        public int[][] getState() {
            return this.board;
        }

        public void play(int x, int y, int player) {
            if (board[x][y] == EMPTY &&
                    x < DIMENSION && y < DIMENSION &&
                    x >= 0 && y >= 0 && !isOver()) {
                board[x][y] = player;
                plays.push(new Point(x, y));
                playmade = true;
            } else {
                playmade = false;
            }
        }

        public void draw(int x, int y, int player) {
            if (board[x][y] == EMPTY &&
                    x < DIMENSION && y < DIMENSION &&
                    x >= 0 && y >= 0) {
                board[x][y] = player;
                plays.push(new Point(x, y));
                playmade = true;
            } else {
                playmade = false;
                System.out.println("Invalid Move");
            }
        }

        // Play move with index
        public void play(int index, int player) {
            int x, y;
            switch (index) {
                case 1:
                    x = 0;
                    y = 0;
                    break;
                case 2:
                    x = 0;
                    y = 1;
                    break;
                case 3:
                    x = 0;
                    y = 2;
                    break;
                case 4:
                    x = 1;
                    y = 0;
                    break;
                case 5:
                    x = 1;
                    y = 1;
                    break;
                case 6:
                    x = 1;
                    y = 2;
                    break;
                case 7:
                    x = 2;
                    y = 0;
                    break;
                case 8:
                    x = 2;
                    y = 1;
                    break;
                case 9:
                    x = 2;
                    y = 2;
                    break;
                default:
                    x = -1;
                    y = -1;
                    break;
            }
            if (board[x][y] == EMPTY &&
                    x < DIMENSION && y < DIMENSION &&
                    x >= 0 && y >= 0 && !isOver()) {
                board[x][y] = player;
                plays.push(new Point(x, y));
                playmade = true;
            } else {
                playmade = false;
            }
        }

        public void undoPlay() {
            Point move;
            if (plays.size() != 0 && playmade) {
                move = plays.pop();
                board[move.x][move.y] = EMPTY;
            }
        }

        public boolean isOver() {
            int numEmpty = 0;
            for (int[] row : board) {
                for (int val : row) {
                    if (val == EMPTY) numEmpty++;
                }
            }
            if (numEmpty == 0) return true;
            if (scorer.scoreboard(this, 1) == WON || scorer.scoreboard(this, 0) == WON) return true;
            return false;
        }

        public List<Point> getChildren() {
            List<Point> children = new ArrayList<Point>();
            for (int row = 0; row < DIMENSION; row++) {
                for (int col = 0; col < DIMENSION; col++) {
                    if (board[row][col] == EMPTY) {
                        children.add(new Point(row, col));
                    }
                }
            }
            return children;
        }
    }

    public static class BigBoard {
        private Board[][] board;
        private int DIMENSION = 3;
        private int currentPlayer;
        private Board currentBoard;
        private Stack<Board> plays;
        private boolean playmade = false;
        private boolean gameOver = false;
        private Evaluator eval = new Evaluator();
        private int currentBoardX;
        private int currentBoardY;


        public BigBoard(int first) {
            this.currentPlayer = first;
            board = new Board[DIMENSION][DIMENSION];
            for (int row = 0; row < DIMENSION; row++) {
                for (int col = 0; col < DIMENSION; col++) {
                    board[row][col] = new Board();
                }
            }
            plays = new Stack<Board>();
        }

        // Get board from coordinates (x,y)
        public Board getBoard(int x, int y) {
            if (x < DIMENSION && y < DIMENSION
                    && x >= 0 && y >= 0) {
                return board[x][y];
            } else {
                System.out.println("Invalid board");
                return null;
            }
        }

        // Get board from cell index
        public Board getBoard(int index) {
            int x, y;
            switch (index) {
                case 1:
                    x = 0;
                    y = 0;
                    break;
                case 2:
                    x = 0;
                    y = 1;
                    break;
                case 3:
                    x = 0;
                    y = 2;
                    break;
                case 4:
                    x = 1;
                    y = 0;
                    break;
                case 5:
                    x = 1;
                    y = 1;
                    break;
                case 6:
                    x = 1;
                    y = 2;
                    break;
                case 7:
                    x = 2;
                    y = 0;
                    break;
                case 8:
                    x = 2;
                    y = 1;
                    break;
                case 9:
                    x = 2;
                    y = 2;
                    break;
                default:
                    x = -1;
                    y = -1;
                    break;
            }

            if (x < DIMENSION && y < DIMENSION
                    && x >= 0 && y >= 0) {
                return board[x][y];
            } else {
                System.out.println("Invalid board");
                return null;
            }
        }


        private void changePlayer() {
            currentPlayer = currentPlayer == 1 ? 0 : 1;
        }

        public void printNineBoard() {
            Board currentBoard;
            int[][] boardState;
            int[][] nineBoard = new int[9][9];

            // Create current state of 9x9 board
            for (int bigX = 0; bigX < DIMENSION; bigX++) {
                for (int bigY = 0; bigY < DIMENSION; bigY++) {

                    currentBoard = getBoard(bigX, bigY);
                    boardState = currentBoard.getState();

                    for (int i = 0; i < DIMENSION; i++) {
                        for (int j = 0; j < DIMENSION; j++) {
                            nineBoard[(bigX * 3) + i][(bigY * 3) + j] = boardState[i][j];
                        }
                    }
                }
            }

            // Print 9x9 Board
            String borderl = " ";
            String borderr = " ";
            for (int row = 0; row < DIMENSION * DIMENSION; row++) {
                for (int col = 0; col < DIMENSION * DIMENSION; col++) {
                    if (nineBoard[row][col] == -1) {
                        System.out.print(borderl + "." + borderr);
                    }
                    if (nineBoard[row][col] == 1) {
                        System.out.print(borderl + "X" + borderr);
                    }
                    if (nineBoard[row][col] == 0) {
                        System.out.print(borderl + "O" + borderr);
                    }
                    if (col == 2 || col == 5) {
                        System.out.print("|");
                    }
                }
                System.out.println();
                if (row == 2 || row == 5) {
                    System.out.println("---------+---------+---------");
                }
            }
            System.out.println();
            System.out.println();


        }

        public boolean isOver() {
            gameOver = false;
            if (eval.score(this, CROSS) == 100 || eval.score(this, NOUGHT) == 100) {
                gameOver = true;
            }
            return gameOver;
        }

        public void play(int index) {
            Point p = indexToCoord(index);
            play(p.x, p.y);
        }

        public void play(int x, int y) {
            if (x < DIMENSION && y < DIMENSION
                    && x >= 0 && y >= 0) {
                currentBoard.play(x, y, currentPlayer);
                plays.push(currentBoard);
                changePlayer();
                if (board[x][y].isOver()) {
                    currentBoard = null;
                } else {
                    currentBoard = board[x][y];
                }
                playmade = true;
            } else {
                playmade = false;
            }
        }

        public Board[][] getBoards() {
            return board;
        }

        public Board getCurrentBoard() {
            return currentBoard;
        }

        public void undoPlay() {
            if (plays.size() != 0 && playmade) {
                Board board = plays.pop();
                board.undoPlay();
                currentBoard = board;
                changePlayer();
            }
        }

        public void setCurrentBoard(int index) {
            Point p = indexToCoord(index);
            currentBoardX = p.x;
            currentBoardY = p.y;
            currentBoard = board[p.x][p.y];
        }

        public void setCurrentBoard(int x, int y) {
            Point p = new Point(x, y);
            currentBoardX = p.x;
            currentBoardY = p.y;
            currentBoard = board[p.x][p.y];
        }

        private Point indexToCoord(int index) {
            int x, y;
            switch (index) {
                case 1:
                    x = 0;
                    y = 0;
                    break;
                case 2:
                    x = 0;
                    y = 1;
                    break;
                case 3:
                    x = 0;
                    y = 2;
                    break;
                case 4:
                    x = 1;
                    y = 0;
                    break;
                case 5:
                    x = 1;
                    y = 1;
                    break;
                case 6:
                    x = 1;
                    y = 2;
                    break;
                case 7:
                    x = 2;
                    y = 0;
                    break;
                case 8:
                    x = 2;
                    y = 1;
                    break;
                case 9:
                    x = 2;
                    y = 2;
                    break;
                default:
                    x = -1;
                    y = -1;
                    break;
            }
            return (new Point(x, y));
        }

        public int getCurrentBoardY() {
            return currentBoardY;
        }

        public int getCurrentBoardX() {
            return currentBoardX;
        }
    }

    public static class Evaluator {


        private int[][] state;
        private int DIMENSION = 3;
        private int EMPTY = -1;
        private int WON = 100;

        public int score(BigBoard nineboard, int player) {
            int value = 0;
            int tempval = 0;

           // nineboard.printNineBoard();

            for (Board[] boards : nineboard.getBoards()) {
                for (Board board : boards) {
                    tempval = scoreboard(board, player);
                    value += scoreboard(board, player);
                }
            }
            //System.out.println(value);
            return value;
        }

        public int scoreboard(Board board, int player) {
            int tempval;
            int value = 0;
            this.state = board.getState();

            //Check rows
            for (int i = 0; i < DIMENSION; i++) {
                if (Math.abs(tempval = scoreLine(state[i], player)) == WON) {
                    return tempval;
                } else {
                    value += tempval;
                }
            }

            //Inverse array
            int[][] invBoard = new int[DIMENSION][DIMENSION];
            for (int row = 0; row < DIMENSION; row++) {
                for (int col = 0; col < DIMENSION; col++) {
                    invBoard[col][row] = state[row][col];
                }
            }

            //Check columns
            for (int i = 0; i < DIMENSION; i++) {
                if (Math.abs(tempval = scoreLine(invBoard[i], player)) == WON) {
                    return tempval;
                } else {
                    value += tempval;
                }
            }

            //check diagonal value
            int[] diag1 = new int[DIMENSION];
            int[] diag2 = new int[DIMENSION];
            for (int i = 0; i < DIMENSION; i++) {
                diag1[i] = state[i][i];
                diag2[i] = state[DIMENSION - i - 1][i];
            }

            if (Math.abs(tempval = scoreLine(diag1, player)) == WON) {
                return tempval;
            } else {
                value += tempval;
            }
            if (Math.abs(tempval = scoreLine(diag2, player)) == WON) {
                return tempval;
            } else {
                value += tempval;
            }
            return value;
        }


        private int scoreLine(int[] line, int player) {
            int seenplayer = -2;
            int numPlayer = 0;
            for (int val : line) {
                if (seenplayer == -2 && val != EMPTY) {
                    seenplayer = val;
                    numPlayer++;
                } else if (val == seenplayer) {
                    numPlayer++;
                } else if (val != seenplayer && val != EMPTY) {
                    return 0;
                }
            }
            if (numPlayer == 3) {
                return seenplayer == player ? WON : -WON;
            } else if (seenplayer == -2) {
                return 0;
            } else {
                return seenplayer == player ? 1 : -1;
            }
        }
    }

    public static class MinMax {

        private Evaluator eval;
        private int currplayer;
        private int oppplayer;
        private BigBoard nineBoard;

        public MinMax(BigBoard _nineBoard) {
            this.nineBoard = _nineBoard;
            eval = new Evaluator();
        }

        private int minMax(BigBoard _board, int _player, int alpha, int beta, int depth) {
            int bestScore;

            if (depth == 0) {
                return eval.score(_board, _player);
            } else {
                if (_player == currplayer) {
                    if (_board.getCurrentBoard() == null) {
                        for (Board[] boards : _board.getBoards()) {
                            for (Board board : boards) {
                                if (board.isOver()) {
                                    continue;
                                }
                                _board.currentBoard = board;
                                for (Point child : board.getChildren()) {
                                    _board.play(child.x, child.y);
                                    alpha = Math.max(alpha, minMax(_board, oppplayer, alpha, beta, depth - 1));
                                    _board.undoPlay();
                                    if (beta <= alpha) break;
                                }

                            }
                        }
                    } else {
                        for (Point child : _board.getCurrentBoard().getChildren()) {
                            _board.play(child.x, child.y);
                            alpha = Math.max(alpha, minMax(_board, oppplayer, alpha, beta, depth - 1));
                            _board.undoPlay();
                            if (beta <= alpha) break;
                        }
                    }
                    bestScore = alpha;
                } else {
                    if (_board.getCurrentBoard() == null) {
                        for (Board[] boards : _board.getBoards()) {
                            for (Board board : boards) {
                                if (board.isOver()) {
                                    continue;
                                }
                                _board.currentBoard = board;
                                for (Point child : board.getChildren()) {
                                    _board.play(child.x, child.y);
                                    beta = Math.min(beta, minMax(_board, currplayer, alpha, beta, depth - 1));
                                    _board.undoPlay();
                                    if (beta <= alpha) break;
                                }

                            }
                        }
                    } else {
                        for (Point child : _board.getCurrentBoard().getChildren()) {
                            _board.play(child.x, child.y);
                            beta = Math.min(beta, minMax(_board, currplayer, alpha, beta, depth - 1));
                            _board.undoPlay();
                            if (beta <= alpha) break;
                        }
                    }
                    bestScore = beta;
                }
            }
            _board.changePlayer();
            return bestScore;
        }

        public Point getBestMove(BigBoard nineboard, int currplayer, int opponent, int searchDepth) {
            this.oppplayer = opponent;
            this.currplayer = currplayer;
            int bestScore = Integer.MIN_VALUE;
            Point bestMove = null;
            // if (nineboard.isOver()) {
            //return 0;
            // }
            if (nineboard.getCurrentBoard() != null) {
                bestMove = getPoint(nineboard, opponent, searchDepth, bestScore, bestMove);
            } else {
                for (int i = 1; i <= 9; i++) {
                    if (nineboard.getBoard(i).isOver()) {
                        continue;
                    }
                    nineboard.setCurrentBoard(i);
                    int score = Integer.MIN_VALUE;
                    Point move = null;
                    move = getPoint(nineboard, opponent, searchDepth, score, move);
                    if (bestScore <= score) {
                        bestScore = score;
                        bestMove = move;
                    }
                }
            }

            if (bestMove != null) {
                nineboard.play(bestMove.x, bestMove.y);
                return bestMove;
            }
            return null;
        }

        private Point getPoint(BigBoard nineboard, int opponent, int searchDepth, int bestScore, Point bestMove) {
            for (Point child : nineboard.getCurrentBoard().getChildren()) {
                nineboard.play(child.x, child.y);
                int score = minMax(nineboard, currplayer,
                        Integer.MIN_VALUE, Integer.MAX_VALUE, searchDepth);
               // System.out.println(child.x + ", " + child.y + " -> " + score);

                if (score > bestScore) {
                    bestScore = score;
                    bestMove = child;
                }
                nineboard.undoPlay();
            }
            return bestMove;
        }
    }
}
