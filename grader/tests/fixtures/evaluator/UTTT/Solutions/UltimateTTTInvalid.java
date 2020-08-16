import java.util.Arrays;
import java.util.Scanner;

public class Main {

	public static void main(String[] args) {
		Decryptor d = new Decryptor();
		d.decode();
	}
}

class Board {
	int rowInput;
	int colInput;
	char[][] board;

	Board() {

	}

	class Pair {
		int row;
		int col;

		Pair(int row, int col) {
			this.row = row;
			this.col = col;
		}
	}

	Board(int row, int col, char[][] b) {
		this.colInput = col;
		this.rowInput = row;
		this.board = b;
	}

	private Pair mapOutParameters(boolean hasOwnChoice) {
		if (!hasOwnChoice) {
			return new Pair(this.rowInput * 3, this.colInput * 3);
		} else {
			Pair pair = this.calculateBestBoard();
			return pair;
		}
	}

	private Pair calculateBestBoard() {
		int counterOfBlank = 0;
		int row = 0;
		int col = 0;
		
		int bestCounter = 0;
		for (int i = 0; i <= 2; i++) {
			for (int j = 0; j <= 2; j++) {
				Board board = getMiniBoard(i, j);
				counterOfBlank = calculateBlankMark(board);

				if (counterOfBlank > bestCounter) {
					bestCounter = counterOfBlank;
					row = i;
					col = j;
				}
			}
		}

		return new Pair(row, col);
	}

	private int calculateBlankMark(Board bestBoard) {
		int counter = 0;
		for (int i = 0; i <= 2; i++) {
			for (int j = 0; j <= 2; j++) {
				if (bestBoard.board[i][j] == '.') {
					counter++;
				}
				if (bestBoard.board[i][j] == '#') {
					return 0;
				}
			}
		}
		return counter;
	}

	Board getMiniBoard(boolean hasOwnChoice) {

		char[][] miniBoard = new char[3][3];
		Pair pair = this.mapOutParameters(hasOwnChoice);
		int miniRow = pair.row;
		int miniCol = pair.col;

		int rSteps = miniRow + 2;
		int cSteps = miniCol + 2;

		int rowMini = 0;
		int colMini = 0;
		for (int rIndex = miniRow; rIndex <= rSteps; rIndex++) {
			for (int cIndex = miniCol; cIndex <= cSteps; cIndex++) {
				miniBoard[rowMini][colMini++] = this.board[rIndex][cIndex];
			}
			rowMini++;
			colMini = 0;
		}

		return new Board(miniRow, miniCol, miniBoard);

	}

	Board getMiniBoard(int miniRow, int miniCol) {

		int rSteps = miniRow + 2;
		int cSteps = miniCol + 2;
		char[][] miniBoard = new char[3][3];

		int rowMini = 0;
		int colMini = 0;
		for (int rIndex = miniRow; rIndex <= rSteps; rIndex++) {
			for (int cIndex = miniCol; cIndex <= cSteps; cIndex++) {
				miniBoard[rowMini][colMini++] = this.board[rIndex][cIndex];
			}
			rowMini++;
			colMini = 0;
		}
		return new Board(0, 0, miniBoard);
	}

	//
	// O...X.#OX
	// .X.X.XOOO
	// X.OOO.###
	// ##XX##OO#
	// OOOXXOXXX
	// #XXXOO###
	// X.X.O..XO
	// OO...X.O.
	// ...O.X.X.

}

class Decryptor {

	private Scanner in = new Scanner(System.in);

	public void decode() {

		int inputRow = in.nextInt();
		int colInput = in.nextInt();
		String dummyline = in.nextLine();

		//
		// O..|.X.|#OX
		// .X.|X.X|OOO
		// X.O|OO.|###
		// ---+---+---
		// ##X|X##|OO#
		// OOO|XXO|XXX
		// #XX|XOO|###
		// ---+---+---
		// X.X|.O.|.XO
		// OO.|..X|.O.
		// ...|O.X|.X.

		int numberOflines = 11;
		char[][] matrix = new char[9][9];
		int matrixRow = 0;
		int matrixCol = 0;
		boolean flag = true;

		for (int lineIndex = 1; lineIndex <= numberOflines; lineIndex++) {
			String line = in.nextLine().trim();
			for (int serial = 0; serial < line.length(); serial++) {
				char symbol = line.charAt(serial);
				if (symbol == '-') {
					flag = false;
					break;
				}
				if (symbol != '|') {
					matrix[matrixRow][matrixCol++] = symbol;
				}
			}
			if (flag) {
				matrixRow++;
			}
			matrixCol = 0;
			flag = true;
		}

		// System.out.println();
		// // extract the matrix here
		// for (int i = 0; i < matrix.length; i++) {
		// for (int j = 0; j < matrix[0].length; j++) {
		// System.out.print(matrix[i][j] + "");
		// }
		// System.out.println();
		// }
		//
		// //System.out.println(Arrays.deepToString(matrix));

		Board board = setBoardFromDecrypting(inputRow, colInput, matrix);

		boolean hasOwnChoice = false;

		if (inputRow == -1 || colInput == -1) {
			hasOwnChoice = true;
		}

		Board miniBoard = board.getMiniBoard(hasOwnChoice);
		Move move = Move.findBestMove(miniBoard.board);
		if(hasOwnChoice){			
			System.out.print(miniBoard.rowInput + " ");
			System.out.print(miniBoard.colInput + " ");
		}
		else{
			System.out.print(inputRow + " ");
			System.out.print(colInput + " ");
		}
		System.out.print(move.getX() + " ");
		System.out.print(move.getY());

	}

	Board setBoardFromDecrypting(int row, int col, char[][] matrix) {
		return new Board(row, col, matrix);
	}

}

class Move {

	private static char PLAYER = 'X';
	private static char OPPONENT = 'O';

	private int x;
	private int y;
	private int value;

	Move() {

	}

	Move(int x, int y) {
		this.setX(x);
		this.setY(y);

	}

	public int getX() {
		return x;
	}

	public void setX(int x) {
		this.x = x;
	}

	public int getY() {
		return y;
	}

	public void setY(int y) {
		this.y = y;
	}

	public String toString() {
		return "x:" + x + "    y:" + y;

	}

	public static boolean hasMovesLeft(char[][] boardoard) {
		for (int i = 0; i < boardoard.length; i++) {
			for (int j = 0; j < boardoard.length; j++) {
				if (boardoard[i][j] == '.') {
					return true;
				}
			}
		}
		return false;
	}

	static int evaluate(char board[][]) {
		// Checking for Rows for X or O victory.
		for (int row = 0; row < 3; row++) {
			if (board[row][0] == board[row][1] && board[row][1] == board[row][2]) {
				if (board[row][0] == PLAYER)
					return +10;
				else if (board[row][0] == OPPONENT)
					return -10;
			}
		}

		// Checking for Columns for X or O victory.
		for (int col = 0; col < 3; col++) {
			if (board[0][col] == board[1][col] && board[1][col] == board[2][col]) {
				if (board[0][col] == PLAYER)
					return +10;

				else if (board[0][col] == OPPONENT)
					return -10;
			}
		}

		// Checking for Diagonals for X or O victory.
		if (board[0][0] == board[1][1] && board[1][1] == board[2][2]) {
			if (board[0][0] == PLAYER)
				return +10;
			else if (board[0][0] == OPPONENT)
				return -10;
		}

		if (board[0][2] == board[1][1] && board[1][1] == board[2][0]) {
			if (board[0][2] == PLAYER)
				return +10;
			else if (board[0][2] == OPPONENT)
				return -10;
		}

		// Else if none of them have won then return 0
		return 0;
	}

	static int minimax(char board[][], int depth, boolean isMax) {
		int score = evaluate(board);

		// If Maximizer has won the game return his/her
		// evaluated score
		if (score == 10)
			return score;

		// If Minimizer has won the game return his/her
		// evaluated score
		if (score == -10)
			return score;

		// If there are no more moves and no winner then
		// it is a tie
		if (hasMovesLeft(board) == false)
			return 0;

		// If this maximizer's move
		if (isMax) {
			int best = -1000;

			// Traverse all cells
			for (int i = 0; i < 3; i++) {
				for (int j = 0; j < 3; j++) {
					// Check if cell is empty
					if (board[i][j] == '.') {
						// Make the move
						board[i][j] = PLAYER;

						// Call minimax recursively and choose
						// the maximum value
						best = Math.max(best, minimax(board, depth + 1, !isMax));

						// Undo the move
						board[i][j] = '.';
					}
				}
			}
			return best;
		}

		// If this minimizer's move
		else {
			int best = 1000;

			// Traverse all cells
			for (int i = 0; i < 3; i++) {
				for (int j = 0; j < 3; j++) {
					// Check if cell is empty
					if (board[i][j] == '.') {
						// Make the move
						board[i][j] = OPPONENT;

						// Call minimax recursively and choose
						// the minimum value
						best = Math.min(best, minimax(board, depth + 1, !isMax));

						// Undo the move
						board[i][j] = '.';
					}
				}
			}
			return best;
		}
	}

	// This will return the best possible move for the player
	public static Move findBestMove(char board[][]) {
		int bestVal = -1000;
		Move bestMove = new Move();
		bestMove.setX(-1);
		bestMove.setX(-1);
		;

		// Traverse all cells, evalutae minimax function for
		// all empty cells. And return the cell with optimal
		// value.
		for (int i = 0; i < 3; i++) {
			for (int j = 0; j < 3; j++) {
				// Check if celll is empty
				if (board[i][j] == '.') {
					// Make the move
					board[i][j] = PLAYER;

					// compute evaluation function for this
					// move.
					int moveVal = minimax(board, 0, false);

					// Undo the move
					board[i][j] = '.';

					// If the value of the current move is
					// more than the best value, then update
					// best/
					if (moveVal > bestVal) {
						bestMove.setX(i);
						bestMove.setY(j);
						bestVal = moveVal;
					}
				}
			}
		}

		bestMove.value = bestVal;
		return bestMove;
	}

}
