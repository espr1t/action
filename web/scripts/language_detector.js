/* Removes spaces after # */
function removeSpaces(code) {
    var curIndex = 0;

    while (curIndex < code.length) {
        if (code[curIndex] == '#') {
            ++curIndex;
            if (curIndex < code.length && code[curIndex] == ' ') {
                var endIndex = curIndex;
                while (endIndex < code.length && code[endIndex] == ' ') ++endIndex;
                code = code.substring(0, curIndex) + code.substring(endIndex);
                curIndex = endIndex - 1;
            }
        }
        ++curIndex;
     }

     return code;
}

/* Removes C style comments and strings from code */
function removeCStyleComments(code) {
    var states = {
        DEFAULT    : 0, // default state
        STRING     : 1, // string state
        SL_COMMENT : 2, // single line comment state
        ML_COMMENT : 3  // multi line comment state
    }

    var curState       = states.DEFAULT;
    var curIndex       = 0;
    var startString    = 0; // start index of a string
    var startMLComment = 0; // start index of a multiline comment
    var startSLComment = 0; // start index of a single line comment

   while (curIndex < code.length) {
        switch (code[curIndex]) {
            case '"':
                if (curState == states.DEFAULT) {
                    startString = curIndex;
                    curState = states.STRING;
                    ++curIndex;
                } else if (curState == states.STRING) {
                    // remove string in double quotes
                    code = code.substring(0, startString + 1) + code.substring(curIndex);
                    curState = states.DEFAULT;
                    curIndex = startString + 2;
                } else ++curIndex;
                break;
            case '\'':
                if (curState == states.DEFAULT) {
                    if ((curIndex + 2) < code.length && code[curIndex + 1] != '\\' && code[curIndex + 2] == '\'') {
                        // remove single character (when not escaping single quote)
                        code = code.substring(0, curIndex + 1) + code.substring(curIndex + 2);
                        curIndex += 3;
                    } else if ((curIndex + 3) < code.length && code[curIndex + 1] == '\\' && code[curIndex + 3] == '\'') {
                        if (code[curIndex + 2] != '\\') {
                            // remove special character
                            code = code.substring(0, curIndex + 1) + code.substring(curIndex + 3);
                        }
                        curIndex += 4;
                    } else ++curIndex;
                } else ++curIndex;
                break;
            case '/':
                if ((curIndex + 1) < code.length && curState == states.DEFAULT) {
                    if (code[curIndex + 1] == '/') {
                        startSLComment = curIndex;
                        curState = states.SL_COMMENT;
                    } else if (code[curIndex + 1] == '*') {
                        startMLComment = curIndex;
                        curState = states.ML_COMMENT;
                    }
                }
                ++curIndex;
                break;
           case '*':
                if ((curIndex + 1) < code.length && code[curIndex + 1] == '/' && curState == states.ML_COMMENT) {
                    // remove multi line comment
                    code = code.substring(0, startMLComment) + (((curIndex + 2) < code.length) ? code.substring(curIndex + 2) : '');
                    curIndex = startMLComment;
                    curState = states.DEFAULT;
                } else ++curIndex;
                break;
            case '\\':
                if (curState == states.STRING || curState == states.SL_COMMENT) {
                    // skip new line
                    if ((curIndex + 1) < code.length && code[curIndex + 1] == '\n') curIndex += 2;
                    else if ((curIndex + 2) < code.length && code[curIndex + 1] == '\r' && code[curIndex + 2] == '\n') curIndex += 3;
                    else ++curIndex;
                 } else ++curIndex;
                break;
            case '\n':
                if (curState == states.SL_COMMENT) {
                    // remove single line comment
                    code = code.substring(0, startSLComment) +  code.substring(curIndex);
                    curIndex = startSLComment;
                    curState = states.DEFAULT;
                } else ++curIndex;
                break;
            case '(':
            case ')':
            case '<':
            case '>':
                // replace brackets with space
                code = code.substring(0, curIndex) + ' ' + code.substring(curIndex + 1);
                break;
            default:
                ++curIndex;
                break;
        }
    }
    code = removeSpaces(code);

    return code;
}

/* Remove Python style comments and strings from code */
function removePyStyleComments(code) {
    var states = {
        DEFAULT    : 0, // default state
        ONE_SQ     : 1, // one single quote state
        THREE_SQ   : 2, // three single quotes state
        ONE_DQ     : 3, // one double quote state
        THREE_DQ   : 4, // three double quotes state
        SL_COMMENT : 5, // single line comment state
    }

    var curState       = states.DEFAULT;
    var curIndex       = 0;
    var startOneSQ     = 0; // start index of a string in one single quote
    var startThreeSQ   = 0; // start index of a string in three single quotes
    var startOneDQ     = 0; // start index of a string in one double quote
    var startThreeDQ   = 0; // start index of a string in three double quotes
    var startSLComment = 0; // start index of a single line comment

    while (curIndex < code.length) {
        switch (code[curIndex]) {
            case '\'':
               if (curState == states.DEFAULT) {
                   if ((curIndex + 2) < code.length && code[curIndex + 1] == '\'' && code[curIndex + 2] == '\'') {
                       startThreeSQ = curIndex;
                       curState = states.THREE_SQ;
                       curIndex += 3;
                   } else {
                       startOneSQ = curIndex;
                       curState = states.ONE_SQ;
                       ++curIndex;
                   }
                } else if (curState == states.ONE_SQ) {
                    // remove string in single quotes
                    code = code.substring(0, startOneSQ + 1) + code.substring(curIndex);
                    curState = states.DEFAULT;
                    curIndex = startOneSQ + 2;
                } else if (curState == states.THREE_SQ) {
                    if ((curIndex + 2) < code.length && code[curIndex + 1] == '\'' && code[curIndex + 2] == '\'') {
                        // remove string in three single quotes
                        code = code.substring(0, startThreeSQ + 3) + code.substring(curIndex);
                        curState = states.DEFAULT;
                        curIndex = startThreeSQ + 6;
                    } else ++curIndex;
                } else ++curIndex;
                break;
            case '"':
                if (curState == states.DEFAULT) {
                    if ((curIndex + 2) < code.length && code[curIndex + 1] == '"' && code[curIndex + 2] == '"') {
                        startThreeDQ = curIndex;
                        curState = states.THREE_DQ;
                        curIndex += 3;
                    } else {
                        startOneDQ = curIndex;
                        curState = states.ONE_DQ;
                        ++curIndex;
                    }
                } else if (curState == states.ONE_DQ) {
                    // remove string in double quotes
                    code = code.substring(0, startOneDQ + 1) + code.substring(curIndex);
                    curState = states.DEFAULT;
                    curIndex = startOneDQ + 2;
                } else if (curState == states.THREE_DQ) {
                    // remove string in three double quotes
                    if ((curIndex + 2) < code.length && code[curIndex + 1] == '"' && code[curIndex + 2] == '"') {
                        code = code.substring(0, startThreeDQ + 3) + code.substring(curIndex);
                        curState = states.DEFAULT;
                        curIndex = startThreeDQ + 6;
                    }
                } else ++curIndex;
                break;
            case '#':
                if ((curIndex + 1) < code.length && curState == states.DEFAULT) {
                    startSLComment = curIndex;
                    curState = states.SL_COMMENT;
                }
                ++curIndex;
                break;
              case '\\':
                if (curState == states.ONE_SQ || curState == states.ONE_DQ || curState == states.SL_COMMENT) {
                    // skip new line
                    if ((curIndex + 1) < code.length && code[curIndex + 1] == '\n') curIndex += 2;
                    else if ((curIndex + 2) < code.length && code[curIndex + 1] == '\r' && code[curIndex + 2] == '\n') curIndex += 3;
                    else ++curIndex;
                 } else ++curIndex;
                break;
            case '\n':
                if (curState == states.SL_COMMENT) {
                    // remove single line comment
                    code = code.substring(0, startSLComment) +  code.substring(curIndex);
                    curIndex = startSLComment;
                    curState = states.DEFAULT;
                } else ++curIndex;
                break;
                default:
                    ++curIndex;
                    break;
        }
    }

    return code;
}

function scoreByKeyword(code, keyword, modifier) {
    let re = new RegExp("(^|\\s)" + keyword + "(\\s|\\(|$)", 'g');
    let numMatches = (code.match(re) || []).length;
    // if (numMatches > 0) {
    //     console.log("Keyword " + keyword + " with modifier " + modifier + " matched " + numMatches + " tokens.");
    // }
    return numMatches > 0 ? modifier : 0;
}

/*
 * Detect language
 * Possible languages: {C++, Java, Python}
 */
function detectLanguage(code, name="SourceName") {
    let keywordScore = {
        STRONG : 3, // keyword in only one language
        WEAK : 1,   // keyword in more than one language
        OTHER: -2   // keyword in another language
    }

    let keywordsCpp = [
        "auto", "bool", "const", "constexpr", "const_cast", "delete", "dynamic_cast", "extern", "friend", "inline",
        "nullptr", "operator", "reinterpret_cast", "signed", "sizeof", "static_cast", "struct", "template", "typedef",
        "typename", "union", "unsigned", "using", "virtual", "include", "std", "cout", "cerr", "endl", "scanf", "fscanf",
        "printf", "fprintf", "define", "pragma"
    ];

    let keywordsCppAndJava = [
        "case", "catch", "char", "default", "do", "this", "double", "enum", "float", "int", "long", "namespace", "new",
        "private", "protected", "public", "short", "static", "switch", "synchronized", "throw", "void", "volatile"
    ];

    let keywordsJava = [
        "abstract", "boolean", "byte", "extends", "final", "finally", "implements", "instanceof", "interface", "native",
        "package", "super", "throws", "transient", "java", "String", "System.out.println", "System.out.printf",
        "public(\\s)+static", "public(\\s)+class", "Scanner", "System.in"
    ];

    let keywordsPython = [
        "as", "def", "elif", "except", "exec", "from", "global", "in", "is", "lambda", "pass", "print", "raise", "with",
        "yield", "range", "xrange", "raw_input"
    ];

    let keywordsPythonAndJava = ["import"];

    let candidates = [
        {
            "language": "C++",
            "code": removeCStyleComments(code),
            "strong": keywordsCpp,
            "weak": keywordsCppAndJava,
            "other": keywordsJava.concat(keywordsPython).concat(keywordsPythonAndJava)
        },
        {
            "language": "Java",
            "code": removeCStyleComments(code),
            "strong": keywordsJava,
            "weak": keywordsCppAndJava,
            "other": keywordsCpp.concat(keywordsPython)
        },
        {
            "language": "Python",
            "code": removePyStyleComments(code),
            "strong": keywordsPython,
            "weak": keywordsPythonAndJava,
            "other": keywordsCpp.concat(keywordsJava).concat(keywordsCppAndJava)
        },
    ];

    let bestScore = 0.0;
    let bestLanguage = "C++";

    console.log("Evaluating file: " + name);
    for (let candidate of candidates) {
        for (let remChar of ['<', '>', '#', ':', ';', '=', '{', '}', '(', ')', '[', ']']) {
            candidate["code"] = candidate["code"].replace(remChar, ' ');
        }

        let score = 0.0;
        for (let token of candidate["weak"]) {
            score += scoreByKeyword(candidate["code"], token, keywordScore.WEAK);
        }
        for (let token of candidate["strong"]) {
            score += scoreByKeyword(candidate["code"], token, keywordScore.STRONG);
        }
        for (let token of candidate["other"]) {
            score += scoreByKeyword(candidate["code"], token, keywordScore.OTHER);
        }

        console.log("  >> language " + candidate["language"] + " got score: " + score);
        if (bestScore < score) {
            bestScore = score;
            bestLanguage = candidate["language"];
        }
    }
    return bestLanguage;
}
