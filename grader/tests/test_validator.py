import os
import unittest
import config
from common import TestInfo, TestStatus
from validator import Validator


class TestValidator(unittest.TestCase):
    PATH_FIXTURES = os.path.abspath("tests/fixtures/validation")

    def test_line_iterator(self):
        text = "This is \r  a line \r\n test \n\rto see\n\n\n\r\nif\r \nthis is working\n."
        expected = ['This is ', '  a line ', ' test ', 'to see', 'if', ' ', 'this is working', '.']
        text_lines = Validator.line_iterator(text)
        self.assertListEqual(list(text_lines), expected)

    def test_token_iterator(self):
        text = "    Some\tweird       0  _42  text with_var_whitespace \ \\ \r\n  other <tokens> . "
        expected = ['Some', 'weird', '0', '_42', 'text', 'with_var_whitespace', '\\', '\\', 'other', '<tokens>', '.']
        text_tokens = Validator.token_iterator(text)
        self.assertListEqual(list(text_tokens), expected)

    def test_abs_relative_float_check(self):
        eps = config.FLOAT_PRECISION

        assert Validator.floats_equal(13.0, 42.0) is False
        assert Validator.floats_equal(42.0, 13.0) is False
        assert Validator.floats_equal(-13.0, 17.0) is False
        assert Validator.floats_equal(13.0, -17.0) is False

        assert Validator.floats_equal(-42.1337, 42.1337) is False

        assert Validator.floats_equal(0.0, 0.0) is True
        assert Validator.floats_equal(0.0, -0.0) is True
        assert Validator.floats_equal(42.1337, 42.1337) is True
        assert Validator.floats_equal(-42.1337, -42.1337) is True

        assert Validator.floats_equal(1234567890.0, 1234567891.0) is True
        assert Validator.floats_equal(-1234567890.0, -1234567891.0) is True
        assert Validator.floats_equal(123456789.0, 123456788.0) is False
        assert Validator.floats_equal(-123456789.0, -123456788.0) is False

        assert Validator.floats_equal(eps, eps * 3.0) is False
        assert Validator.floats_equal(-eps, -eps * 3.0) is False
        assert Validator.floats_equal(eps / 10, eps / 10 * 9.0) is True
        assert Validator.floats_equal(-eps / 10, -eps / 10 * 9.0) is True

        assert Validator.floats_equal(0.0, 0.0 + eps) is True
        assert Validator.floats_equal(0.0, 0.0 - eps) is True
        assert Validator.floats_equal(0.0, 0.0 + eps + eps / 10) is False
        assert Validator.floats_equal(0.0, 0.0 - eps - eps / 10) is False

        assert Validator.floats_equal(+0.000000001, +0.000000009) is False
        assert Validator.floats_equal(-0.000000001, -0.000000009) is False
        assert Validator.floats_equal(+0.0000000001, +0.0000000009) is True
        assert Validator.floats_equal(-0.0000000001, -0.0000000009) is True

        assert Validator.floats_equal(4.2e10, 4.3e10) is False
        assert Validator.floats_equal(-4.2e10, -4.3e10) is False
        assert Validator.floats_equal(4.2e-10, 4.3e-10) is True
        assert Validator.floats_equal(-4.2e-10, -4.3e-10) is True

        assert Validator.floats_equal(-1.337426661317e42, -1.337426661317e45) is False
        assert Validator.floats_equal(-1.337426661317e-42, -1.337426661317e-45) is True

        assert Validator.floats_equal(1337.42666, 1337.42667) is False
        assert Validator.floats_equal(-1337.42666, -1337.42667) is False
        assert Validator.floats_equal(1337.426666, 1337.426667) is True
        assert Validator.floats_equal(-1337.426666, -1337.426667) is True

        # Integers
        assert Validator.floats_equal(123456789010111213, 123456789010111214) is True
        assert Validator.floats_equal(-123456789010111213, -123456789010111214) is True

    @staticmethod
    def create_test_info(sol_path):
        test_info = TestInfo(
            inpFile="",
            inpHash="",
            solFile=os.path.basename(sol_path),
            solHash="SomeRandomString",
            position=0
        )
        test_info.solPath = sol_path
        return test_info

    def test_absolute_comparison(self):
        test = self.create_test_info(os.path.join(self.PATH_FIXTURES, "FloatAbsolute.sol"))

        # Absolute difference is less than 10^-9
        with open(os.path.join(self.PATH_FIXTURES, "FloatAbsoluteOK.out"), "rb") as file:
            output = file.read()
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=True)
        self.assertEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(1.0, validator_result.score)
        self.assertIs(TestStatus.ACCEPTED, validator_result.status)

        # Absolute difference is greater than 10^-9
        with open(os.path.join(self.PATH_FIXTURES, "FloatAbsoluteWA.out"), "rb") as file:
            output = file.read()
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=True)
        self.assertNotEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(0.0, validator_result.score)
        self.assertIs(TestStatus.WRONG_ANSWER, validator_result.status)

    def test_relative_comparison(self):
        test = self.create_test_info(os.path.join(self.PATH_FIXTURES, "FloatRelative.sol"))

        # Relative difference is less than 10^-9
        with open(os.path.join(self.PATH_FIXTURES, "FloatRelativeOK.out"), "rb") as file:
            output = file.read()
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=True)
        self.assertEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(1.0, validator_result.score)
        self.assertIs(TestStatus.ACCEPTED, validator_result.status)

        # Relative difference is greater than 10^-9
        with open(os.path.join(self.PATH_FIXTURES, "FloatRelativeWA.out"), "rb") as file:
            output = file.read()
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=True)
        self.assertNotEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(0.0, validator_result.score)
        self.assertIs(TestStatus.WRONG_ANSWER, validator_result.status)

    def test_leading_zeroes(self):
        # Answers with missing leading zeroes are treated okay if floating point comparison is enabled
        test = self.create_test_info(os.path.join(self.PATH_FIXTURES, "FloatLeadingZeroes.sol"))
        with open(os.path.join(self.PATH_FIXTURES, "FloatLeadingZeroes.out"), "rb") as file:
            output = file.read()
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=True)
        self.assertEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(1.0, validator_result.score)
        self.assertIs(TestStatus.ACCEPTED, validator_result.status)

        # However, they should fail if the floating point comparison is disabled
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=False)
        self.assertNotEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(0.0, validator_result.score)
        self.assertIs(TestStatus.WRONG_ANSWER, validator_result.status)

    def test_very_large_integers(self):
        # Differences in the last digits of large numbers (> 2^53) are okay if abs/rel comparison is enabled
        test = self.create_test_info(os.path.join(self.PATH_FIXTURES, "FloatVeryLargeIntegers.sol"))
        with open(os.path.join(self.PATH_FIXTURES, "FloatVeryLargeIntegers.out"), "rb") as file:
            output = file.read()
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=True)
        self.assertEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(1.0, validator_result.score)
        self.assertIs(TestStatus.ACCEPTED, validator_result.status)

        # However, they should fail if the floating point comparison is disabled
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=False)
        self.assertNotEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(0.0, validator_result.score)
        self.assertIs(TestStatus.WRONG_ANSWER, validator_result.status)

    def test_ascii_text_comparison(self):
        test = self.create_test_info(os.path.join(self.PATH_FIXTURES, "TextAscii.sol"))

        # If text is the same as the expected one, the answer is obviously okay
        with open(os.path.join(self.PATH_FIXTURES, "TextAsciiOK.out"), "rb") as file:
            output = file.read()
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=False)
        self.assertEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(1.0, validator_result.score)
        self.assertIs(TestStatus.ACCEPTED, validator_result.status)

        # Trailing spaces at the end of the lines or after the last line are also okay
        with open(os.path.join(self.PATH_FIXTURES, "TextAsciiPE.out"), "rb") as file:
            output = file.read()
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=False)
        self.assertEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(1.0, validator_result.score)
        self.assertIs(TestStatus.ACCEPTED, validator_result.status)

        # Differences in printable characters, however, are not okay
        with open(os.path.join(self.PATH_FIXTURES, "TextAsciiWA1.out"), "rb") as file:
            output = file.read()
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=False)
        self.assertNotEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(0.0, validator_result.score)
        self.assertIs(TestStatus.WRONG_ANSWER, validator_result.status)

        # Having unicode symbols (which are different than the expected) are also not okay
        with open(os.path.join(self.PATH_FIXTURES, "TextAsciiWA2.out"), "rb") as file:
            output = file.read()
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=False)
        self.assertNotEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(0.0, validator_result.score)
        self.assertIs(TestStatus.WRONG_ANSWER, validator_result.status)

    def test_unicode_text_comparison(self):
        test = self.create_test_info(os.path.join(self.PATH_FIXTURES, "TextUnicode.sol"))

        # If text is the same as the expected one, the answer is obviously okay
        with open(os.path.join(self.PATH_FIXTURES, "TextUnicodeOK.out"), "rb") as file:
            output = file.read()
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=False)
        self.assertEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(1.0, validator_result.score)
        self.assertIs(TestStatus.ACCEPTED, validator_result.status)

        # Trailing spaces before or at the end of the lines or after the last line are also okay
        with open(os.path.join(self.PATH_FIXTURES, "TextUnicodePE.out"), "rb") as file:
            output = file.read()
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=False)
        self.assertEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(1.0, validator_result.score)
        self.assertIs(TestStatus.ACCEPTED, validator_result.status)

        # Differences in printable characters, however, no matter how small are not okay
        with open(os.path.join(self.PATH_FIXTURES, "TextUnicodeWA1.out"), "rb") as file:
            output = file.read()
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=False)
        self.assertNotEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(0.0, validator_result.score)
        self.assertIs(TestStatus.WRONG_ANSWER, validator_result.status)

        # Differences in printable unicode characters are also not okay
        with open(os.path.join(self.PATH_FIXTURES, "TextUnicodeWA2.out"), "rb") as file:
            output = file.read()
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=False)
        self.assertNotEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(0.0, validator_result.score)
        self.assertIs(TestStatus.WRONG_ANSWER, validator_result.status)

    def test_empty_output(self):
        test = self.create_test_info(os.path.join(self.PATH_FIXTURES, "TextEmpty.sol"))

        # Empty output is okay
        with open(os.path.join(self.PATH_FIXTURES, "TextEmptyOK.out"), "rb") as file:
            output = file.read()
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=False)
        self.assertEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(1.0, validator_result.score)
        self.assertIs(TestStatus.ACCEPTED, validator_result.status)

        # Only whitespace is also okay
        with open(os.path.join(self.PATH_FIXTURES, "TextEmptyPE.out"), "rb") as file:
            output = file.read()
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=False)
        self.assertEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(1.0, validator_result.score)
        self.assertIs(TestStatus.ACCEPTED, validator_result.status)

        # Non-empty output is not okay
        with open(os.path.join(self.PATH_FIXTURES, "TextEmptyWA.out"), "rb") as file:
            output = file.read()
        validator_result = Validator.validate_output_directly(test=test, output=output, compare_floats=False)
        self.assertNotEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(0.0, validator_result.score)
        self.assertIs(TestStatus.WRONG_ANSWER, validator_result.status)

    def test_output_from_checker_or_tester(self):
        # Empty output is not okay
        validator_result = Validator.validate_output_from_checker_or_tester(42, b"")
        self.assertEqual("", validator_result.info)
        self.assertNotEqual("", validator_result.error)
        self.assertEqual(0.0, validator_result.score)
        self.assertIs(TestStatus.INTERNAL_ERROR, validator_result.status)

        # Single line with score is not okay
        validator_result = Validator.validate_output_from_checker_or_tester(42, b"0.42")
        self.assertEqual("", validator_result.info)
        self.assertNotEqual("", validator_result.error)
        self.assertEqual(0.0, validator_result.score)
        self.assertIs(TestStatus.INTERNAL_ERROR, validator_result.status)

        # Single line with verdict
        validator_result = Validator.validate_output_from_checker_or_tester(42, b"OK\n")
        self.assertEqual("", validator_result.info)
        self.assertEqual("Checker or tester's output didn't contain verdict or score!", validator_result.error)
        self.assertEqual(0.0, validator_result.score)
        self.assertIs(TestStatus.INTERNAL_ERROR, validator_result.status)

        # Valid verdict and score is okay
        validator_result = Validator.validate_output_from_checker_or_tester(42, b"OK\n0.42")
        self.assertEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(0.42, validator_result.score)
        self.assertIs(TestStatus.ACCEPTED, validator_result.status)

        # Valid verdict and score greater than 1 is okay
        validator_result = Validator.validate_output_from_checker_or_tester(42, b"OK\n133742")
        self.assertEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(133742, validator_result.score)
        self.assertIs(TestStatus.ACCEPTED, validator_result.status)

        # Valid verdict and negative score is okay
        validator_result = Validator.validate_output_from_checker_or_tester(42, b"OK\n-13")
        self.assertEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(-13, validator_result.score)
        self.assertIs(TestStatus.ACCEPTED, validator_result.status)

        # Verdict and score in reverse order are still fine (OK)
        validator_result = Validator.validate_output_from_checker_or_tester(42, b"0.42\nOK")
        self.assertEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(0.42, validator_result.score)
        self.assertIs(TestStatus.ACCEPTED, validator_result.status)

        # Verdict and score in reverse order are still fine (WA)
        validator_result = Validator.validate_output_from_checker_or_tester(42, b"0.0\nWA")
        self.assertEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(0.0, validator_result.score)
        self.assertIs(TestStatus.WRONG_ANSWER, validator_result.status)

        # Non-numeric score is not okay
        validator_result = Validator.validate_output_from_checker_or_tester(42, b"OK\nfoo")
        self.assertEqual("", validator_result.info)
        self.assertNotEqual("", validator_result.error)
        self.assertEqual(0.0, validator_result.score)
        self.assertIs(TestStatus.INTERNAL_ERROR, validator_result.status)

        # All non-OK verdicts have zero score.
        validator_result = Validator.validate_output_from_checker_or_tester(42, b"WA\n133742")
        self.assertEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(0.0, validator_result.score)
        self.assertIs(TestStatus.WRONG_ANSWER, validator_result.status)

        # IE is interpreted as Internal Error
        validator_result = Validator.validate_output_from_checker_or_tester(42, b"IE\n-1")
        self.assertEqual("", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(0.0, validator_result.score)
        self.assertIs(TestStatus.INTERNAL_ERROR, validator_result.status)

        # Not recognized verdict (OK/WA/IE) is considered Internal Error
        validator_result = Validator.validate_output_from_checker_or_tester(42, b"GL\n0.42")
        self.assertEqual("", validator_result.info)
        self.assertEqual("Checker or tester's verdict line seems invalid (value = 'GL')!", validator_result.error)
        self.assertEqual(0.0, validator_result.score)
        self.assertIs(TestStatus.INTERNAL_ERROR, validator_result.status)

        # The optional third line is recorded in the validator result info field
        validator_result = Validator.validate_output_from_checker_or_tester(42, b"OK\n0.42\nNeeded 42 queries.")
        self.assertEqual("Needed 42 queries.", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(0.42, validator_result.score)
        self.assertIs(TestStatus.ACCEPTED, validator_result.status)

        # There can be more than one info lines
        validator_result = Validator.validate_output_from_checker_or_tester(42, b"OK\n0.42\nNeeded 42 queries.\nWon.")
        self.assertEqual("Needed 42 queries.\nWon.", validator_result.info)
        self.assertEqual("", validator_result.error)
        self.assertEqual(0.42, validator_result.score)
        self.assertIs(TestStatus.ACCEPTED, validator_result.status)
