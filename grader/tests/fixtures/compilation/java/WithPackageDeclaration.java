
import java.util.Scanner;

public class Main {
	public static final int COLUMNS = 10;
	public static final int ROWS = 20;
	
	public static void main(String args[]) {
		Scanner scanner = new Scanner(System.in);
		int size = scanner.nextInt();
		char[] elements = Utils.parseElements(scanner.nextLine(), size);
		scanner.close();
		
		Solver solver = new Solver(COLUMNS, ROWS);
		System.out.println(solver.solve(elements));	
	}
	private static class Utils {
	
	private static final double AGGREGATE_HEIGHT = -0.51006;
	private static final double COMPLETE_LINES = 0.760666;
	private static final double HOLES = -0.35663;
	private static final double BUMPINESS = -0.184483;
	

	public static char[] parseElements(String elements, int size) {
		if (elements == null || size == 0) {
			return new char[0];
		}
		char[] elementsArray = new char[size];
		for (int i = 0; i < size; i ++) {
			elementsArray[i] = elements.charAt(i * 2);
		}

		return elementsArray;
	}
	
	public static boolean placeElement1(boolean[][] board, int column, int rotation) {
		if (column >= board.length) {
			return false;
		}
		int row = findFistAvailableRow(board[column]);
		
		if (row == -1) {
			return false;
		}
		if (rotation == 0) {
			if (row + 3 >= board[column].length) {
				return false;
			}
			for (int i = row; i < 4; i++) {
				board[column][i] = true;
			}
			
			return true;
		} else if (rotation == 1) {
			if (column + 3 >= board.length) {
				return false;
			}
			
			for (int i = 0; i < board[column].length; i++) {
				if(!board[column][i] &&
				   !board[column + 1][i] &&
				   !board[column + 2][i] &&
				   !board[column + 3][i]) {
					board[column][i] = true;
					board[column + 1][i] = true;
					board[column + 2][i] = true;
					board[column + 3][i] = true;
					return true;
				}
			}
			return false;
		}
		
		return false;
	}
	
	public static boolean placeElement2(boolean[][] board, int column, int rotation) {
		if (column >= board.length) {
			return false;
		}
		if (rotation == 0) {
			if(column + 1 >= board.length) {
				return false;
			}
			for (int i = 0; i < board[column].length - 2; i++) {
				if (!board[column][i] &&
				   !board[column][i + 1] &&
				   !board[column][i + 2] &&
				   !board[column + 1][i + 2]) {
					board[column][i] = true;
					board[column][i + 1] = true;
					board[column][i + 2] = true;
					board[column + 1][i + 2] = true;
					return true;
				}
			}
			return false;
		} else if (rotation == 1) {
			if (column + 2 >= board.length) {
				return false;
			}
			for (int i = 1; i < board[column].length; i++) {
				if (!board[column][i] && 
					!board[column + 1][i] &&
					!board[column + 2][i] &&
					!board[column + 2][i - 1]) {
					board[column][i] = true;
					board[column + 1][i] = true;
					board[column + 2][i] = true;
					board[column + 2][i - 1] = true;
					return true;
				}
			}
			return false;
		} else if (rotation == 2) {
			if (column + 1 >= board.length) {
				return false;
			}
			for (int i = 0; i < board[column].length - 2; i++) {
				if (!board[column][i] && 
					!board[column + 1][i] &&
					!board[column + 1][i + 1] &&
					!board[column + 1][i + 2]) {
					board[column][i] = true;
					board[column + 1][i] = true;
					board[column + 1][i + 1] = true;
					board[column + 1][i + 2] = true;
					return true;
				}
			}
			return false;
		} else if (rotation == 3) {
			if (column + 2 >= board.length) {
				return false;
			}
			for (int i = 0; i < board[column].length - 1; i++) {
				if (!board[column][i] && 
					!board[column][i + 1] &&
					!board[column + 1][i] &&
					!board[column + 2][i]) {
					board[column][i] = true;
					board[column][i + 1] = true;
					board[column + 1][i] = true;
					board[column + 2][i] = true;
					return true;
				}
			}
			
			return false;
		}
		
		return false;
	}
	
	public static boolean placeElement3(boolean[][] board, int column, int rotation) {
		if (column >= board.length) {
			return false;
		}
		
		if (rotation == 0) {
			if (column + 1 >= board.length) {
				return false;
			}
			
			for  (int i = 0; i < board[column].length - 2; i++) {
				if (!board[column + 1][i] &&
				    !board[column + 1][i + 1] &&
				    !board[column + 1][i + 2] &&
				    !board[column][i + 2]) {
					board[column + 1][i] = true;
					board[column + 1][i + 1] = true;
					board[column + 1][i + 2] = true;
					board[column][i + 2] = true;
					return true;
				}
			}
			return false;
		} else if (rotation == 1) {
			if(column + 2 >= board.length) {
				return false;
			}
			for (int i = 0; i < board[column].length - 1; i++) {
				if (!board[column][i] && 
					!board[column + 1][i] &&
					!board[column + 2][i] &&
					!board[column + 2][i + 1]) {
					board[column][i] = true;
					board[column + 1][i] = true;
					board[column + 2][i] = true;
					board[column + 2][i + 1] = true;
					return true;
				}
			}
			return false;
		} else if (rotation == 2) {
			if (column + 1 >= board.length) {
				return false;
			}
			for (int i = 0; i < board[column].length - 2; i++) {
				if (!board[column][i] && 
					!board[column + 1][i] &&
					!board[column][i + 1] &&
					!board[column][i + 2]) {
					board[column][i] = true;
					board[column + 1][i] = true;
					board[column][i + 1] = true;
					board[column][i + 2] = true;
					return true;
				}
			}
			return false;
		} else if (rotation == 3) {
			if (column + 2 >= board.length) {
				return false;
			}
			for (int i = 0; i < board[column].length - 1; i++) {
				if (!board[column][i] && 
					!board[column][i + 1] &&
					!board[column + 1][i + 1] &&
					!board[column + 2][i + 1]) {
					board[column][i] = true;
					board[column][i + 1] = true;
					board[column + 1][i + 1] = true;
					board[column + 2][i + 1] = true;
					return true;
				}
			}
		}
		
		return false;
	}
	
	public static boolean placeElement4(boolean[][] board, int column) {
		if (column + 1 >= board.length) {
			return false;
		}
		
		for (int i = 0; i < board[column].length - 1; i++) {
			if(!board[column][i] &&
			   !board[column][i + 1] &&
			   !board[column + 1][i] &&
			   !board[column + 1][i + 1]) {
				board[column][i] = true;
				board[column][i + 1] = true;
				board[column + 1][i] = true;
				board[column + 1][i + 1] = true;
				return true;
			}
		}
		
		return false;
	}
	
	public static boolean placeElement5(boolean[][] board, int column, int rotation) {
		if (column + 1 >= board.length) {
			return false;
		}
		if (rotation == 0) {
			for (int i = 1; i < board[column].length - 1; i++) {
				if (!board[column][i] &&
					!board[column][i + 1] &&
					!board[column + 1][i] &&
					!board[column + 1][i - 1]) {
					board[column][i] = true;
					board[column][i + 1] = true;
					board[column + 1][i] = true;
					board[column + 1][i - 1] = true;
					return true;
				}
			}
		} else if (rotation == 1) {
			if (column + 2 >= board.length) {
				return false;
			}
			for (int i = 0; i < board[column].length - 1; i++) {
				if (!board[column][i] &&
					!board[column + 1][i] &&
					!board[column + 1][i + 1] &&
					!board[column + 2][i + 1]) {
					board[column][i] = true;
					board[column + 1][i] = true;
					board[column + 1][i + 1] = true;
					board[column + 2][i + 1] = true;
					
					return true;
				}
			}
		}

		return false;
	}
	
	public static boolean placeElement6(boolean[][] board, int column, int rotation) {
		if (rotation == 0) {
			if (column + 1 >= board.length) {
				return false;
			}
			
			for (int i = 0; i < board[column].length - 2; i++) {
				if (!board[column][i] &&
					!board[column][i + 1] &&
					!board[column][i + 2] &&
					!board[column + 1][i + 1]) {
					board[column][i] = true;
					board[column][i + 1] = true;
					board[column][i + 2] = true;
					board[column + 1][i + 1] = true;
					return true;
				}
			}
		} else if(rotation == 1) {
			if (column + 2 >= board.length) {
				return false;
			}
			
			for (int i = 1; i < board[column].length; i++) {
				if(!board[column][i] &&
				   !board[column + 1][i] &&
				   !board[column + 2][i] &&
				   !board[column + 1][i - 1]) {
					board[column][i] = true;
					board[column + 1][i] = true;
					board[column + 2][i] = true;
					board[column + 1][i - 1] = true;
					return true;
				}
			}
		} else if(rotation == 2) {
			if (column + 1 >= board.length) {
				return false;
			}
			for (int i = 1; i < board[column].length - 1; i++) {
				if(!board[column][i] &&
			       !board[column + 1][i] &&
			       !board[column + 1][i + 1] &&
			       !board[column + 1][i - 1]) {
					board[column][i] = true;
					board[column + 1][i] = true;
					board[column + 1][i + 1] = true;
					board[column + 1][i - 1] = true;
					return true;
				}
			}
		} else if(rotation == 3) {
			if (column + 2 >= board.length) {
				return false;
			}
			
			for (int i = 0; i < board[column].length - 1; i++) {
				if(!board[column][i] &&
				   !board[column + 1][i] &&
				   !board[column + 2][i] &&
				   !board[column + 1][i + 1]) {
					board[column][i] = true;
					board[column + 1][i] = true;
					board[column + 2][i] = true;
					board[column + 1][i + 1] = true;
					return true;
				}
			}
		}
		return false;
	}
	
	public static boolean placeElement7(boolean[][] board, int column, int rotation) {
		if (rotation == 0) {
			if (column + 1 >= board.length) {
				return false;
			}
			for (int i = 0; i < board.length - 2; i++) {
				if (!board[column][i] &&
					!board[column][i + 1] &&
					!board[column + 1][i + 1] &&
					!board[column + 1][i + 2]) {
					board[column][i] = true;
					board[column][i + 1] = true;
					board[column + 1][i + 1] = true;
					board[column + 1][i + 2] = true;
					return true;
				}
			}
		} else if(rotation == 1) {
			if (column + 2 >= board.length) {
				return false;
			}
			for (int i = 1; i < board.length; i++) {
				if (!board[column][i] &&
					!board[column + 1][i] &&
					!board[column + 1][i - 1] &&
					!board[column + 2][i - 1]) {
					board[column][i] = true;
					board[column + 1][i] = true;
					board[column + 1][i - 1] = true;
					board[column + 2][i - 1] = true;
					return true;
				}
			}
		}
		
		return false;
	}
	
	public static int findFistAvailableRow(boolean[] column) {
		for (int i = 0; i < column.length; i++) {
			if (!column[i]) {
				return i;
			}
			
		}
		return -1;
	}
	
	public static double fitness(boolean[][] board) {
		return AGGREGATE_HEIGHT * aggregateHeight(board) +
			   COMPLETE_LINES * completeLines(board) +
			   HOLES * holes(board) +
			   BUMPINESS * bumpiness(board);
	}
	
	public static int aggregateHeight(boolean[][] board) {
		int height = 0;
		for(int i = 0; i < board.length; i++) {
			int columnHeight = findHighestBlock(board[i]);
			if (columnHeight == -1) {
				continue;
			}
			height += columnHeight + 1;
		}
		
		return height;
	}
	
	public static int completeLines(boolean[][] board) {
		int lines = 0;
		for (int i = 0; i < board[0].length; i++) {
			boolean hasCompleteLine = true;
			for (int j = 0; j < board.length; j++) {
				if (!board[j][i]) {
					hasCompleteLine = false;
					break;
				}

			}
			if (hasCompleteLine) {
				lines++;
			}
		}
		
		return lines;
	}
	
	public static int findHighestBlock(boolean[] column) {
		for (int i = column.length - 1; i >= 0; i--) {
			if (column[i]) {
				return i;
			}
		}
		
		return -1;
	}
	
	public static int holes(boolean[][] board) {
		int holes = 0;
		for (int i = 0; i < board.length; i++) {
			int highestBlock = findHighestBlock(board[i]);
			for (int j = highestBlock; j >= 0; j--) {
				if (!board[i][j]) {
					holes++;
				}
			}
		}
		
		return holes;
	}
	
	public static int bumpiness(boolean[][] board) {
		int bumpiness = 0;
		for (int i = 0; i < board.length - 1; i++) {
			int highest = findHighestBlock(board[i]);
			int nextRowHighest = findHighestBlock(board[i + 1]);
			if (highest == -1) {
				highest = 0;
			} else {
				highest++;
			}
			if (nextRowHighest == -1) {
				nextRowHighest = 0;
			} else {
				nextRowHighest++;
			}
			bumpiness += Math.abs(highest - nextRowHighest); 
		}
		
		return bumpiness;
	}
	
	public static void printBoard(boolean[][] board) {
		for (int i = 0; i < board.length; i++) {
			System.out.print("{");
			for (int j = 0; j < board[i].length; j++) {
				System.out.print(board[i][j] + " ");
			}
			System.out.println("},");
		}
		System.out.println();
	}
}
	
	private static class PlacementException extends RuntimeException{
		private static final long serialVersionUID = 1L;
		
		public PlacementException() {
			super("Game over");
		}
	}
	
	private static class Solver {
		private static final double LOW_NEGATIVE_NUMBER = -1000d;
		private final int rows;
		private final int columns;

		private boolean board[][];

		public Solver(int columns, int rows) {
			if (columns <= 0) {
				throw new IllegalArgumentException("columns can only be a positive number");
			}
			
			if (rows <= 0) {
				throw new IllegalArgumentException("rows can only be a positive number");
			}
			
			this.columns = columns;
			this.rows = rows;


			board = new boolean[columns][];
			for (int i = 0; i < columns; i++) {
				board[i] = new boolean[rows];
			}
		}

		public String solve(char[] elements) {
			if (elements == null) {
				return null;
			}
			StringBuilder builder = new StringBuilder(elements.length * 4);
			for (char element : elements) {
				try {
					MoveData move = placeElement(element);
					builder.append(move.getRotation());
					builder.append(" ");
					builder.append(move.getColumn());
					builder.append(" ");
					removeCompleteLines();
				} catch (Exception e) {
					return builder.toString();
				}
				
			}

			return builder.toString();
		}
		
		private MoveData placeElement(char element) {
			switch(element) {
				case '1' :
					return placeElement1();
				case '2' :
					return placeElement2();
				case '3' :
					return placeElement3();
				case '4' :
					return placeElement4();
				case '5' :
					return placeElement5();
				case '6' :
					return placeElement6();
				case '7' :
					return placeElement7();
			
			}
			throw new PlacementException();
		}
		
		private MoveData placeElement1() {
			MoveData move = new MoveData();
			double maxFitness = LOW_NEGATIVE_NUMBER;
			boolean placed = false;
			boolean[][] boardCopy = getBoardCopy();
			boolean[][] newBoard = getBoardCopy();
			
			for (int i = 0; i < columns; i++) {
				boolean placedRotation0 = Utils.placeElement1(boardCopy, i, 0);
				if (placedRotation0) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(0);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
				
				boolean placedRotation1 = Utils.placeElement1(boardCopy, i, 1);
				if (placedRotation1) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(1);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
			}
			if (!placed) {
				throw new PlacementException();
			}
			makeTheSame(board, newBoard);
			return move;
		}
		
		private MoveData placeElement2() {
			MoveData move = new MoveData();
			double maxFitness = LOW_NEGATIVE_NUMBER;
			boolean placed = false;
			boolean[][] boardCopy = getBoardCopy();
			boolean[][] newBoard = getBoardCopy();
			
			for (int i = 0; i < columns; i++) {
				boolean placedRotation0 = Utils.placeElement2(boardCopy, i, 0);
				if (placedRotation0) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(0);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
				
				boolean placedRotation1 = Utils.placeElement2(boardCopy, i, 1);
				if (placedRotation1) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(1);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
				
				boolean placedRotation2 = Utils.placeElement2(boardCopy, i, 2);
				if (placedRotation2) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(2);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
				
				boolean placedRotation3 = Utils.placeElement2(boardCopy, i, 3);
				if (placedRotation3) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(3);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
			}
			
			if (!placed) {
				throw new PlacementException();
			}
			makeTheSame(board, newBoard);
			return move;
		}
		
		private MoveData placeElement3() {
			MoveData move = new MoveData();
			double maxFitness = LOW_NEGATIVE_NUMBER;
			boolean placed = false;
			boolean[][] boardCopy = getBoardCopy();
			boolean[][] newBoard = getBoardCopy();
			
			for (int i = 0; i < columns; i++) {
				boolean placedRotation0 = Utils.placeElement3(boardCopy, i, 0);
				if (placedRotation0) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(0);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
				
				boolean placedRotation1 = Utils.placeElement3(boardCopy, i, 1);
				if (placedRotation1) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(1);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
				
				boolean placedRotation2 = Utils.placeElement3(boardCopy, i, 2);
				if (placedRotation2) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(2);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
				
				boolean placedRotation3 = Utils.placeElement3(boardCopy, i, 3);
				if (placedRotation3) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(3);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
			}
			
			if (!placed) {
				throw new PlacementException();
			}
			makeTheSame(board, newBoard);
			return move;
		}
		
		private MoveData placeElement4() {
			MoveData move = new MoveData();
			double maxFitness = LOW_NEGATIVE_NUMBER;
			boolean placed = false;
			boolean[][] boardCopy = getBoardCopy();
			boolean[][] newBoard = getBoardCopy();
			
			for (int i = 0; i < columns; i++) {
				boolean placedRotation0 = Utils.placeElement4(boardCopy, i);
				if (placedRotation0) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(0);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
			}
			
			if (!placed) {
				throw new PlacementException();
			}
			makeTheSame(board, newBoard);
			return move;
		}
		
		private MoveData placeElement5() {
			MoveData move = new MoveData();
			double maxFitness = LOW_NEGATIVE_NUMBER;
			boolean placed = false;
			boolean[][] boardCopy = getBoardCopy();
			boolean[][] newBoard = getBoardCopy();
			
			for (int i = 0; i < columns; i++) {
				boolean placedRotation0 = Utils.placeElement5(boardCopy, i, 0);
				if (placedRotation0) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(0);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
				
				boolean placedRotation1 = Utils.placeElement5(boardCopy, i, 1);
				if (placedRotation1) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(1);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
			}
			
			if (!placed) {
				throw new PlacementException();
			}
			makeTheSame(board, newBoard);
			return move;
		}
		
		private MoveData placeElement6() {
			MoveData move = new MoveData();
			double maxFitness = LOW_NEGATIVE_NUMBER;
			boolean placed = false;
			boolean[][] boardCopy = getBoardCopy();
			boolean[][] newBoard = getBoardCopy();
			
			for (int i = 0; i < columns; i++) {
				boolean placedRotation0 = Utils.placeElement6(boardCopy, i, 0);
				if (placedRotation0) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(0);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
				
				boolean placedRotation1 = Utils.placeElement6(boardCopy, i, 1);
				if (placedRotation1) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(1);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
				
				boolean placedRotation2 = Utils.placeElement6(boardCopy, i, 2);
				if (placedRotation2) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(2);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
				
				boolean placedRotation3 = Utils.placeElement6(boardCopy, i, 3);
				if (placedRotation3) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(3);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
			}
			
			if (!placed) {
				throw new PlacementException();
			}
			makeTheSame(board, newBoard);
			return move;
		}
		
		private MoveData placeElement7() {
			MoveData move = new MoveData();
			double maxFitness = LOW_NEGATIVE_NUMBER;
			boolean placed = false;
			boolean[][] boardCopy = getBoardCopy();
			boolean[][] newBoard = getBoardCopy();
			
			for (int i = 0; i < columns; i++) {
				boolean placedRotation0 = Utils.placeElement7(boardCopy, i, 0);
				if (placedRotation0) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(0);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
				
				boolean placedRotation1 = Utils.placeElement7(boardCopy, i, 1);
				if (placedRotation1) {
					placed = true;
					double fitness = Utils.fitness(boardCopy);
					if (fitness >= maxFitness) {
						maxFitness = fitness;
						move.setColumn(i);
						move.setRotation(1);
						makeTheSame(newBoard, boardCopy);
					}
					makeTheSame(boardCopy, board);
				}
			}
			
			if (!placed) {
				throw new PlacementException();
			}
			makeTheSame(board, newBoard);
			return move;
		}
		
		private void removeCompleteLines() {
			for (int i = 0; i < board[0].length; i++) {
				boolean hasCompleteLine = true;
				for (int j = 0; j < board.length; j++) {
					if (!board[j][i]) {
						hasCompleteLine = false;
						break;
					}

				}
				if (hasCompleteLine) {
					for (int q = 0; q < board.length; q++) {
						board[q][i] = false;
					}
				}
			}
		}
		
		private boolean[][] getBoardCopy() {
			boolean[][] copy = new boolean[columns][];
			for (int i = 0; i < columns; i++) {
				copy[i] = new boolean[rows];
				for (int j = 0; j < rows; j++) {
					copy[i][j] = board[i][j];
				}
			}
			
			return copy;
		}
		
		private void makeTheSame(boolean[][] current, boolean[][] wanted) {
			for (int i = 0; i < current.length; i++) {
				for (int j = 0; j < current[i].length; j++) {
					current[i][j] = wanted[i][j];
				}
			}
		}

	}
	public static class MoveData {
		private int rotation;
		private int column;
		
		public MoveData() {
			
		}
		
		public MoveData(int rotation, int column) {
			this.rotation = rotation;
			this.column = column;
		}

		public int getRotation() {
			return rotation;
		}

		public void setRotation(int rotation) {
			this.rotation = rotation;
		}
		
		public int getColumn() {
			return column;
		}

		public void setColumn(int column) {
			this.column = column;
		}

		@Override
		public String toString() {
			char[] data = new char[3];
			data[0] = Character.forDigit(rotation, 10);
			data[1] = ' ';
			data[2] = Character.forDigit(column, 10);
			return String.valueOf(data);
		}
	}
}
