#include <iostream>
#include <vector>
#include <algorithm>
#include <string>

using namespace std;
struct matrix
{
	char pos[3][3];
};

void cinRow(char* row)
{
	cin >> row[0] >> row[1] >> row[2];
}

int main()
{


	int rB, cB;

	cin >> rB >> cB;
	cin.ignore();

	matrix a[3][3];

	vector<string> buff(11);
	for (size_t i = 0; i < 11; i++)
	{
		getline(cin, buff[i]);
	}
	buff.erase(buff.begin() + 7);
	buff.erase(buff.begin() + 3);
	for (size_t i = 0; i < 9; i++)
	{
		buff[i].erase(buff[i].begin() + 7);
		buff[i].erase(buff[i].begin() + 3);
	}

	if (rB != -1)
	{
		while (true)
		{
			int i = rand() % 3;
			int j = rand() % 3;
			if (buff[3*rB + i][3*cB + j] == '.')
				{
					cout << rB << " " << cB << " "
					<< i << " " << j << " " << endl;
					return 0;
				}
		}
	}
	else
	{
		for (int i = 0; i < 3; ++i)
		{
			for (int j = 0; j < 3; ++j)
			{
				for (size_t k = 0; k < 3; k++)
				{
					for (size_t l = 0; l < 3; l++)
					{
						if (buff[k + i*3][l + j*3] == '.')
						{
							cout << i << " " << j << " "
								<< k << " " << l << " " << endl;
							return 0;
						}
					}
				}
			}
		}
	}
}


