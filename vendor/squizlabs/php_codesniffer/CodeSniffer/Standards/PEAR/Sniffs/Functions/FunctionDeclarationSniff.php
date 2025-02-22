<?php
/**
 * PEAR_Sniffs_Functions_FunctionDeclarationSniff.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

/**
 * PEAR_Sniffs_Functions_FunctionDeclarationSniff.
 *
 * Ensure single and multi-line function declarations are defined correctly.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class PEAR_Sniffs_Functions_FunctionDeclarationSniff implements PHP_CodeSniffer_Sniff
{

    /**
     * The number of spaces code should be indented.
     */
    public int $indent = 4;


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return array(
                T_FUNCTION,
                T_CLOSURE,
               );

    }//end register()


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if (!isset($tokens[$stackPtr]['parenthesis_opener'])
            || !isset($tokens[$stackPtr]['parenthesis_closer'])
            || $tokens[$stackPtr]['parenthesis_opener'] === null
            || $tokens[$stackPtr]['parenthesis_closer'] === null
        ) {
            return;
        }

        $openBracket  = $tokens[$stackPtr]['parenthesis_opener'];
        $closeBracket = $tokens[$stackPtr]['parenthesis_closer'];

        // Must be one space after the FUNCTION keyword.
        if ($tokens[($stackPtr + 1)]['content'] === $phpcsFile->eolChar) {
            $spaces = 'newline';
        } elseif ($tokens[($stackPtr + 1)]['code'] === T_WHITESPACE) {
            $spaces = strlen($tokens[($stackPtr + 1)]['content']);
        } else {
            $spaces = 0;
        }

        if ($spaces !== 1) {
            $error = 'Expected 1 space after FUNCTION keyword; %s found';
            $data  = array($spaces);
            $fix   = $phpcsFile->addFixableError($error, $stackPtr, 'SpaceAfterFunction', $data);
            if ($fix) {
                if ($spaces === 0) {
                    $phpcsFile->fixer->addContent($stackPtr, ' ');
                } else {
                    $phpcsFile->fixer->replaceToken(($stackPtr + 1), ' ');
                }
            }
        }

        // Must be one space before the opening parenthesis. For closures, this is
        // enforced by the first check because there is no content between the keywords
        // and the opening parenthesis.
        if ($tokens[$stackPtr]['code'] === T_FUNCTION) {
            if ($tokens[($openBracket - 1)]['content'] === $phpcsFile->eolChar) {
                $spaces = 'newline';
            } elseif ($tokens[($openBracket - 1)]['code'] === T_WHITESPACE) {
                $spaces = strlen($tokens[($openBracket - 1)]['content']);
            } else {
                $spaces = 0;
            }

            if ($spaces !== 0) {
                $error = 'Expected 0 spaces before opening parenthesis; %s found';
                $data  = array($spaces);
                $fix   = $phpcsFile->addFixableError($error, $openBracket, 'SpaceBeforeOpenParen', $data);
                if ($fix) {
                    $phpcsFile->fixer->replaceToken(($openBracket - 1), '');
                }
            }
        }//end if

        // Must be one space before and after USE keyword for closures.
        if ($tokens[$stackPtr]['code'] === T_CLOSURE) {
            $use = $phpcsFile->findNext(T_USE, ($closeBracket + 1), $tokens[$stackPtr]['scope_opener']);
            if ($use !== false) {
                if ($tokens[($use + 1)]['code'] !== T_WHITESPACE) {
                    $length = 0;
                } elseif ($tokens[($use + 1)]['content'] === "\t") {
                    $length = '\t';
                } else {
                    $length = strlen($tokens[($use + 1)]['content']);
                }

                if ($length !== 1) {
                    $error = 'Expected 1 space after USE keyword; found %s';
                    $data  = array($length);
                    $fix   = $phpcsFile->addFixableError($error, $use, 'SpaceAfterUse', $data);
                    if ($fix) {
                        if ($length === 0) {
                            $phpcsFile->fixer->addContent($use, ' ');
                        } else {
                            $phpcsFile->fixer->replaceToken(($use + 1), ' ');
                        }
                    }
                }

                if ($tokens[($use - 1)]['code'] !== T_WHITESPACE) {
                    $length = 0;
                } elseif ($tokens[($use - 1)]['content'] === "\t") {
                    $length = '\t';
                } else {
                    $length = strlen($tokens[($use - 1)]['content']);
                }

                if ($length !== 1) {
                    $error = 'Expected 1 space before USE keyword; found %s';
                    $data  = array($length);
                    $fix   = $phpcsFile->addFixableError($error, $use, 'SpaceBeforeUse', $data);
                    if ($fix) {
                        if ($length === 0) {
                            $phpcsFile->fixer->addContentBefore($use, ' ');
                        } else {
                            $phpcsFile->fixer->replaceToken(($use - 1), ' ');
                        }
                    }
                }
            }//end if
        }//end if

        if ($this->isMultiLineDeclaration($phpcsFile, $stackPtr, $openBracket, $tokens) === true) {
            $this->processMultiLineDeclaration($phpcsFile, $stackPtr, $tokens);
        } else {
            $this->processSingleLineDeclaration($phpcsFile, $stackPtr, $tokens);
        }

    }//end process()


    /**
     * Determine if this is a multi-line function declaration.
     *
     * @param PHP_CodeSniffer_File $phpcsFile   The file being scanned.
     * @param int                  $stackPtr    The position of the current token
     *                                          in the stack passed in $tokens.
     * @param int                  $openBracket The position of the opening bracket
     *                                          in the stack passed in $tokens.
     * @param array                $tokens      The stack of tokens that make up
     *                                          the file.
     *
     * @return void
     */
    public function isMultiLineDeclaration(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $openBracket, $tokens)
    {
        $closeBracket = $tokens[$openBracket]['parenthesis_closer'];
        if ($tokens[$openBracket]['line'] !== $tokens[$closeBracket]['line']) {
            return true;
        }

        // Closures may use the USE keyword and so be multi-line in this way.
        if ($tokens[$stackPtr]['code'] === T_CLOSURE) {
            $use = $phpcsFile->findNext(T_USE, ($closeBracket + 1), $tokens[$stackPtr]['scope_opener']);
            if ($use !== false) {
                // If the opening and closing parenthesis of the use statement
                // are also on the same line, this is a single line declaration.
                $open  = $phpcsFile->findNext(T_OPEN_PARENTHESIS, ($use + 1));
                $close = $tokens[$open]['parenthesis_closer'];
                if ($tokens[$open]['line'] !== $tokens[$close]['line']) {
                    return true;
                }
            }
        }

        return false;

    }//end isMultiLineDeclaration()


    /**
     * Processes single-line declarations.
     *
     * Just uses the Generic BSD-Allman brace sniff.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     * @param array                $tokens    The stack of tokens that make up
     *                                        the file.
     *
     * @return void
     */
    public function processSingleLineDeclaration(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $tokens)
    {
        if ($tokens[$stackPtr]['code'] === T_CLOSURE) {
            if (!class_exists('Generic_Sniffs_Functions_OpeningFunctionBraceKernighanRitchieSniff', true)) {
                throw new PHP_CodeSniffer_Exception('Class Generic_Sniffs_Functions_OpeningFunctionBraceKernighanRitchieSniff not found');
            }

            $sniff = new Generic_Sniffs_Functions_OpeningFunctionBraceKernighanRitchieSniff();
        } else {
            if (!class_exists('Generic_Sniffs_Functions_OpeningFunctionBraceBsdAllmanSniff', true)) {
                throw new PHP_CodeSniffer_Exception('Class Generic_Sniffs_Functions_OpeningFunctionBraceBsdAllmanSniff not found');
            }

            $sniff = new Generic_Sniffs_Functions_OpeningFunctionBraceBsdAllmanSniff();
        }

        $sniff->checkClosures = true;
        $sniff->process($phpcsFile, $stackPtr);

    }//end processSingleLineDeclaration()


    /**
     * Processes multi-line declarations.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     * @param array                $tokens    The stack of tokens that make up
     *                                        the file.
     *
     * @return void
     */
    public function processMultiLineDeclaration(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $tokens)
    {
        // We need to work out how far indented the function
        // declaration itself is, so we can work out how far to
        // indent parameters.
        $functionIndent = 0;
        for ($i = ($stackPtr - 1); $i >= 0; $i--) {
            if ($tokens[$i]['line'] !== $tokens[$stackPtr]['line']) {
                $i++;
                break;
            }
        }

        if ($tokens[$i]['code'] === T_WHITESPACE) {
            $functionIndent = strlen($tokens[$i]['content']);
        }

        // The closing parenthesis must be on a new line, even
        // when checking abstract function definitions.
        $closeBracket = $tokens[$stackPtr]['parenthesis_closer'];
        $prev         = $phpcsFile->findPrevious(
            T_WHITESPACE,
            ($closeBracket - 1),
            null,
            true
        );

        if ($tokens[$closeBracket]['line'] !== $tokens[$tokens[$closeBracket]['parenthesis_opener']]['line'] && $tokens[$prev]['line'] === $tokens[$closeBracket]['line']) {
            $error = 'The closing parenthesis of a multi-line function declaration must be on a new line';
            $fix   = $phpcsFile->addFixableError($error, $closeBracket, 'CloseBracketLine');
            if ($fix) {
                $phpcsFile->fixer->addNewlineBefore($closeBracket);
            }
        }

        // If this is a closure and is using a USE statement, the closing
        // parenthesis we need to look at from now on is the closing parenthesis
        // of the USE statement.
        if ($tokens[$stackPtr]['code'] === T_CLOSURE) {
            $use = $phpcsFile->findNext(T_USE, ($closeBracket + 1), $tokens[$stackPtr]['scope_opener']);
            if ($use !== false) {
                $open         = $phpcsFile->findNext(T_OPEN_PARENTHESIS, ($use + 1));
                $closeBracket = $tokens[$open]['parenthesis_closer'];

                $prev = $phpcsFile->findPrevious(
                    T_WHITESPACE,
                    ($closeBracket - 1),
                    null,
                    true
                );

                if ($tokens[$closeBracket]['line'] !== $tokens[$tokens[$closeBracket]['parenthesis_opener']]['line'] && $tokens[$prev]['line'] === $tokens[$closeBracket]['line']) {
                    $error = 'The closing parenthesis of a multi-line use declaration must be on a new line';
                    $fix   = $phpcsFile->addFixableError($error, $closeBracket, 'UseCloseBracketLine');
                    if ($fix) {
                        $phpcsFile->fixer->addNewlineBefore($closeBracket);
                    }
                }
            }//end if
        }//end if

        // Each line between the parenthesis should be indented 4 spaces.
        $openBracket = $tokens[$stackPtr]['parenthesis_opener'];
        $lastLine    = $tokens[$openBracket]['line'];
        for ($i = ($openBracket + 1); $i < $closeBracket; $i++) {
            if ($tokens[$i]['line'] !== $lastLine) {
                if ($i === $tokens[$stackPtr]['parenthesis_closer']
                    || ($tokens[$i]['code'] === T_WHITESPACE
                    && (($i + 1) === $closeBracket
                    || ($i + 1) === $tokens[$stackPtr]['parenthesis_closer']))
                ) {
                    // Closing braces need to be indented to the same level
                    // as the function.
                    $expectedIndent = $functionIndent;
                } else {
                    $expectedIndent = ($functionIndent + $this->indent);
                }

                // We changed lines, so this should be a whitespace indent token.
                if ($tokens[$i]['code'] !== T_WHITESPACE) {
                    $foundIndent = 0;
                } elseif ($tokens[$i]['line'] !== $tokens[($i + 1)]['line']) {
                    // This is an empty line, so don't check the indent.
                    $foundIndent = $expectedIndent;
                    $error = 'Blank lines are not allowed in a multi-line function declaration';
                    $fix   = $phpcsFile->addFixableError($error, $i, 'EmptyLine');
                    if ($fix) {
                        $phpcsFile->fixer->replaceToken($i, '');
                    }
                } else {
                    $foundIndent = strlen($tokens[$i]['content']);
                }

                if ($expectedIndent !== $foundIndent) {
                    $error = 'Multi-line function declaration not indented correctly; expected %s spaces but found %s';
                    $data  = array(
                              $expectedIndent,
                              $foundIndent,
                             );

                    $fix = $phpcsFile->addFixableError($error, $i, 'Indent', $data);
                    if ($fix) {
                        $spaces = str_repeat(' ', $expectedIndent);
                        if ($foundIndent === 0) {
                            $phpcsFile->fixer->addContentBefore($i, $spaces);
                        } else {
                            $phpcsFile->fixer->replaceToken($i, $spaces);
                        }
                    }
                }

                $lastLine = $tokens[$i]['line'];
            }//end if

            if ($tokens[$i]['code'] === T_ARRAY || $tokens[$i]['code'] === T_OPEN_SHORT_ARRAY) {
                // Skip arrays as they have their own indentation rules.
                $i = $tokens[$i]['code'] === T_OPEN_SHORT_ARRAY ? $tokens[$i]['bracket_closer'] : $tokens[$i]['parenthesis_closer'];

                $lastLine = $tokens[$i]['line'];
                continue;
            }
        }//end for

        if (!isset($tokens[$stackPtr]['scope_opener'])) {
            return;
        }

        // The opening brace needs to be one space away from the closing parenthesis.
        $opener = $tokens[$stackPtr]['scope_opener'];
        if ($tokens[$opener]['line'] !== $tokens[$closeBracket]['line']) {
            $error = 'The closing parenthesis and the opening brace of a multi-line function declaration must be on the same line';
            $fix   = $phpcsFile->addFixableError($error, $opener, 'NewlineBeforeOpenBrace');
            if ($fix) {
                $prev = $phpcsFile->findPrevious(PHP_CodeSniffer_Tokens::$emptyTokens, ($opener - 1), $closeBracket, true);
                $phpcsFile->fixer->beginChangeset();
                $phpcsFile->fixer->addContent($prev, ' {');
                $phpcsFile->fixer->replaceToken($opener, '');
                $phpcsFile->fixer->endChangeset();
            }
        } else {
            $prev = $tokens[($opener - 1)];
            $length = $prev['code'] !== T_WHITESPACE ? 0 : strlen($prev['content']);

            if ($length !== 1) {
                $error = 'There must be a single space between the closing parenthesis and the opening brace of a multi-line function declaration; found %s spaces';
                $fix   = $phpcsFile->addFixableError($error, ($opener - 1), 'SpaceBeforeOpenBrace', array($length));
                if ($fix) {
                    if ($length === 0) {
                        $phpcsFile->fixer->addContentBefore($opener, ' ');
                    } else {
                        $phpcsFile->fixer->replaceToken(($opener - 1), ' ');
                    }
                }

                return;
            }//end if
        }//end if

    }//end processMultiLineDeclaration()


}//end class
