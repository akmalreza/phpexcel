<?php
/**
 * PEAR_Sniffs_Functions_FunctionCallSignatureSniff.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */

/**
 * PEAR_Sniffs_Functions_FunctionCallSignatureSniff.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class PEAR_Sniffs_Functions_FunctionCallSignatureSniff implements PHP_CodeSniffer_Sniff
{

    /**
     * A list of tokenizers this sniff supports.
     */
    public array $supportedTokenizers = array(
                                   'PHP',
                                   'JS',
                                  );

    /**
     * The number of spaces code should be indented.
     */
    public int $indent = 4;

    /**
     * If TRUE, multiple arguments can be defined per line in a multi-line call.
     */
    public bool $allowMultipleArguments = true;

    /**
     * How many spaces should follow the opening bracket.
     */
    public int $requiredSpacesAfterOpen = 0;

    /**
     * How many spaces should precede the closing bracket.
     */
    public int $requiredSpacesBeforeClose = 0;


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return PHP_CodeSniffer_Tokens::$functionNameTokens;

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
        $this->requiredSpacesAfterOpen   = (int) $this->requiredSpacesAfterOpen;
        $this->requiredSpacesBeforeClose = (int) $this->requiredSpacesBeforeClose;
        $tokens = $phpcsFile->getTokens();

        // Find the next non-empty token.
        $openBracket = $phpcsFile->findNext(PHP_CodeSniffer_Tokens::$emptyTokens, ($stackPtr + 1), null, true);

        if ($tokens[$openBracket]['code'] !== T_OPEN_PARENTHESIS) {
            // Not a function call.
            return;
        }

        if (!isset($tokens[$openBracket]['parenthesis_closer'])) {
            // Not a function call.
            return;
        }

        // Find the previous non-empty token.
        $search   = PHP_CodeSniffer_Tokens::$emptyTokens;
        $search[] = T_BITWISE_AND;
        $previous = $phpcsFile->findPrevious($search, ($stackPtr - 1), null, true);
        if ($tokens[$previous]['code'] === T_FUNCTION) {
            // It's a function definition, not a function call.
            return;
        }

        $closeBracket = $tokens[$openBracket]['parenthesis_closer'];

        if (($stackPtr + 1) !== $openBracket) {
            // Checking this: $value = my_function[*](...).
            $error = 'Space before opening parenthesis of function call prohibited';
            $fix   = $phpcsFile->addFixableError($error, $stackPtr, 'SpaceBeforeOpenBracket');
            if ($fix) {
                $phpcsFile->fixer->beginChangeset();
                for ($i = ($stackPtr + 1); $i < $openBracket; $i++) {
                    $phpcsFile->fixer->replaceToken($i, '');
                }

                // Modify the bracket as well to ensure a conflict if the bracket
                // has been changed in some way by another sniff.
                $phpcsFile->fixer->replaceToken($openBracket, '(');
                $phpcsFile->fixer->endChangeset();
            }
        }

        $next = $phpcsFile->findNext(T_WHITESPACE, ($closeBracket + 1), null, true);
        if ($tokens[$next]['code'] === T_SEMICOLON && isset(PHP_CodeSniffer_Tokens::$emptyTokens[$tokens[($closeBracket + 1)]['code']])) {
            $error = 'Space after closing parenthesis of function call prohibited';
            $fix   = $phpcsFile->addFixableError($error, $closeBracket, 'SpaceAfterCloseBracket');
            if ($fix) {
                $phpcsFile->fixer->beginChangeset();
                for ($i = ($closeBracket + 1); $i < $next; $i++) {
                    $phpcsFile->fixer->replaceToken($i, '');
                }

                // Modify the bracket as well to ensure a conflict if the bracket
                // has been changed in some way by another sniff.
                $phpcsFile->fixer->replaceToken($closeBracket, ')');
                $phpcsFile->fixer->endChangeset();
            }
        }

        // Check if this is a single line or multi-line function call.
        if ($this->isMultiLineCall($phpcsFile, $stackPtr, $openBracket, $tokens) === true) {
            $this->processMultiLineCall($phpcsFile, $stackPtr, $openBracket, $tokens);
        } else {
            $this->processSingleLineCall($phpcsFile, $stackPtr, $openBracket, $tokens);
        }

    }//end process()


    /**
     * Determine if this is a multi-line function call.
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
    public function isMultiLineCall(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $openBracket, $tokens)
    {
        $closeBracket = $tokens[$openBracket]['parenthesis_closer'];
        return $tokens[$openBracket]['line'] !== $tokens[$closeBracket]['line'];

    }//end isMultiLineCall()


    /**
     * Processes single-line calls.
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
    public function processSingleLineCall(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $openBracket, $tokens)
    {
        $closer = $tokens[$openBracket]['parenthesis_closer'];
        if ($openBracket === ($closer - 1)) {
            return;
        }

        if ($this->requiredSpacesAfterOpen === 0 && $tokens[($openBracket + 1)]['code'] === T_WHITESPACE) {
            // Checking this: $value = my_function([*]...).
            $error = 'Space after opening parenthesis of function call prohibited';
            $fix   = $phpcsFile->addFixableError($error, $stackPtr, 'SpaceAfterOpenBracket');
            if ($fix) {
                $phpcsFile->fixer->replaceToken(($openBracket + 1), '');
            }
        } elseif ($this->requiredSpacesAfterOpen > 0) {
            $spaceAfterOpen = 0;
            if ($tokens[($openBracket + 1)]['code'] === T_WHITESPACE) {
                $spaceAfterOpen = strlen($tokens[($openBracket + 1)]['content']);
            }
            if ($spaceAfterOpen !== $this->requiredSpacesAfterOpen) {
                $error = 'Expected %s spaces after opening bracket; %s found';
                $data  = array(
                          $this->requiredSpacesAfterOpen,
                          $spaceAfterOpen,
                         );
                $fix   = $phpcsFile->addFixableError($error, $stackPtr, 'SpaceAfterOpenBracket', $data);
                if ($fix) {
                    $padding = str_repeat(' ', $this->requiredSpacesAfterOpen);
                    if ($spaceAfterOpen === 0) {
                        $phpcsFile->fixer->addContent($openBracket, $padding);
                    } else {
                        $phpcsFile->fixer->replaceToken(($openBracket + 1), $padding);
                    }
                }
            }
        }//end if

        // Checking this: $value = my_function(...[*]).
        $spaceBeforeClose = 0;
        $prev = $phpcsFile->findPrevious(T_WHITESPACE, ($closer - 1), $openBracket, true);
        if ($tokens[$prev]['code'] === T_END_HEREDOC || $tokens[$prev]['code'] === T_END_NOWDOC) {
            // Need a newline after these tokens, so ignore this rule.
            return;
        }

        if ($tokens[$prev]['line'] !== $tokens[$closer]['line']) {
            $spaceBeforeClose = 'newline';
        } elseif ($tokens[($closer - 1)]['code'] === T_WHITESPACE) {
            $spaceBeforeClose = strlen($tokens[($closer - 1)]['content']);
        }

        if ($spaceBeforeClose !== $this->requiredSpacesBeforeClose) {
            $error = 'Expected %s spaces before closing bracket; %s found';
            $data  = array(
                      $this->requiredSpacesBeforeClose,
                      $spaceBeforeClose,
                     );
            $fix   = $phpcsFile->addFixableError($error, $stackPtr, 'SpaceBeforeCloseBracket', $data);
            if ($fix) {
                $padding = str_repeat(' ', $this->requiredSpacesBeforeClose);

                if ($spaceBeforeClose === 0) {
                    $phpcsFile->fixer->addContentBefore($closer, $padding);
                } elseif ($spaceBeforeClose === 'newline') {
                    $phpcsFile->fixer->beginChangeset();
                    $closingContent = ')';
                    $next = $phpcsFile->findNext(T_WHITESPACE, ($closer + 1), null, true);
                    if ($tokens[$next]['code'] === T_SEMICOLON) {
                        $closingContent .= ';';
                        for ($i = ($closer + 1); $i <= $next; $i++) {
                            $phpcsFile->fixer->replaceToken($i, '');
                        }
                    }
                    // We want to jump over any whitespace or inline comment and
                    // move the closing parenthesis after any other token.
                    $prev = ($closer - 1);
                    while (isset(PHP_CodeSniffer_Tokens::$emptyTokens[$tokens[$prev]['code']])) {
                        if (($tokens[$prev]['code'] === T_COMMENT)
                            && (strpos($tokens[$prev]['content'], '*/') !== false)
                        ) {
                            break;
                        }

                        $prev--;
                    }
                    $phpcsFile->fixer->addContent($prev, $padding.$closingContent);
                    $prevNonWhitespace = $phpcsFile->findPrevious(T_WHITESPACE, ($closer - 1), null, true);
                    for ($i = ($prevNonWhitespace + 1); $i <= $closer; $i++) {
                        $phpcsFile->fixer->replaceToken($i, '');
                    }
                    $phpcsFile->fixer->endChangeset();
                } else {
                    $phpcsFile->fixer->replaceToken(($closer - 1), $padding);
                }//end if
            }//end if
        }//end if

    }//end processSingleLineCall()


    /**
     * Processes multi-line calls.
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
    public function processMultiLineCall(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $openBracket, $tokens)
    {
        // We need to work out how far indented the function
        // call itself is, so we can work out how far to
        // indent the arguments.
        $start = $phpcsFile->findStartOfStatement($stackPtr);
        foreach (array('stackPtr', 'start') as $checkToken) {
            $x = $$checkToken;
            for ($i = ($x - 1); $i >= 0; $i--) {
                if ($tokens[$i]['line'] !== $tokens[$x]['line']) {
                    $i++;
                    break;
                }
            }

            if ($i <= 0) {
                $functionIndent = 0;
            } elseif ($tokens[$i]['code'] === T_WHITESPACE) {
                $functionIndent = strlen($tokens[$i]['content']);
            } elseif ($tokens[$i]['code'] === T_CONSTANT_ENCAPSED_STRING) {
                $functionIndent = 0;
            } else {
                $trimmed = ltrim($tokens[$i]['content']);
                if ($trimmed === '') {
                    $functionIndent = $tokens[$i]['code'] === T_INLINE_HTML ? strlen($tokens[$i]['content']) : $tokens[$i]['column'] - 1;
                } else {
                    $functionIndent = (strlen($tokens[$i]['content']) - strlen($trimmed));
                }
            }

            $varName  = $checkToken.'Indent';
            $$varName = $functionIndent;
        }//end foreach

        $functionIndent = max($startIndent, $stackPtrIndent);

        $next = $phpcsFile->findNext(PHP_CodeSniffer_Tokens::$emptyTokens, ($openBracket + 1), null, true);
        if ($tokens[$next]['line'] === $tokens[$openBracket]['line']) {
            $error = 'Opening parenthesis of a multi-line function call must be the last content on the line';
            $fix   = $phpcsFile->addFixableError($error, $stackPtr, 'ContentAfterOpenBracket');
            if ($fix) {
                $phpcsFile->fixer->addContent(
                    $openBracket,
                    $phpcsFile->eolChar.str_repeat(' ', ($functionIndent + $this->indent))
                );
            }
        }

        $closeBracket = $tokens[$openBracket]['parenthesis_closer'];
        $prev         = $phpcsFile->findPrevious(T_WHITESPACE, ($closeBracket - 1), null, true);
        if ($tokens[$prev]['line'] === $tokens[$closeBracket]['line']) {
            $error = 'Closing parenthesis of a multi-line function call must be on a line by itself';
            $fix   = $phpcsFile->addFixableError($error, $closeBracket, 'CloseBracketLine');
            if ($fix) {
                $phpcsFile->fixer->addContentBefore(
                    $closeBracket,
                    $phpcsFile->eolChar.str_repeat(' ', ($functionIndent + $this->indent))
                );
            }
        }

        // Each line between the parenthesis should be indented n spaces.
        $lastLine = ($tokens[$openBracket]['line'] - 1);
        $argStart = null;
        $argEnd   = null;
        $inArg    = false;

        // Start processing at the first argument.
        $i = $phpcsFile->findNext(T_WHITESPACE, ($openBracket + 1), null, true);
        if ($tokens[($i - 1)]['code'] === T_WHITESPACE
            && $tokens[($i - 1)]['line'] === $tokens[$i]['line']
        ) {
            // Make sure we check the indent.
            $i--;
        }

        for ($i; $i < $closeBracket; $i++) {
            $inArg = $i > $argStart && $i < $argEnd;

            if ($tokens[$i]['line'] !== $lastLine) {
                $lastLine = $tokens[$i]['line'];

                // Ignore heredoc indentation.
                if (isset(PHP_CodeSniffer_Tokens::$heredocTokens[$tokens[$i]['code']])) {
                    continue;
                }

                // Ignore multi-line string indentation.
                if (isset(PHP_CodeSniffer_Tokens::$stringTokens[$tokens[$i]['code']])
                    && $tokens[$i]['code'] === $tokens[($i - 1)]['code']
                ) {
                    continue;
                }

                // Ignore inline HTML.
                if ($tokens[$i]['code'] === T_INLINE_HTML) {
                    continue;
                }

                if ($tokens[$i]['line'] !== $tokens[$openBracket]['line']) {
                    // We changed lines, so this should be a whitespace indent token, but first make
                    // sure it isn't a blank line because we don't need to check indent unless there
                    // is actually some code to indent.
                    if ($tokens[$i]['code'] === T_WHITESPACE) {
                        $nextCode = $phpcsFile->findNext(T_WHITESPACE, ($i + 1), ($closeBracket + 1), true);
                        if ($tokens[$nextCode]['line'] !== $lastLine) {
                            if (!$inArg) {
                                $error = 'Empty lines are not allowed in multi-line function calls';
                                $fix   = $phpcsFile->addFixableError($error, $i, 'EmptyLine');
                                if ($fix) {
                                    $phpcsFile->fixer->replaceToken($i, '');
                                }
                            }

                            continue;
                        }
                    } else {
                        $nextCode = $i;
                    }

                    if ($tokens[$nextCode]['line'] === $tokens[$closeBracket]['line']) {
                        // Closing brace needs to be indented to the same level
                        // as the function call.
                        $inArg          = false;
                        $expectedIndent = $functionIndent;
                    } else {
                        $expectedIndent = ($functionIndent + $this->indent);
                    }

                    if ($tokens[$i]['code'] !== T_WHITESPACE
                        && $tokens[$i]['code'] !== T_DOC_COMMENT_WHITESPACE
                    ) {
                        // Just check if it is a multi-line block comment. If so, we can
                        // calculate the indent from the whitespace before the content.
                        if ($tokens[$i]['code'] === T_COMMENT
                            && $tokens[($i - 1)]['code'] === T_COMMENT
                        ) {
                            $trimmedLength = strlen(ltrim($tokens[$i]['content']));
                            if ($trimmedLength === 0) {
                                // This is a blank comment line, so indenting it is
                                // pointless.
                                continue;
                            }

                            $foundIndent = (strlen($tokens[$i]['content']) - $trimmedLength);
                        } else {
                            $foundIndent = 0;
                        }
                    } else {
                        $foundIndent = strlen($tokens[$i]['content']);
                    }

                    if ($foundIndent < $expectedIndent
                        || (!$inArg
                        && $expectedIndent !== $foundIndent)
                    ) {
                        $error = 'Multi-line function call not indented correctly; expected %s spaces but found %s';
                        $data  = array(
                                  $expectedIndent,
                                  $foundIndent,
                                 );

                        $fix = $phpcsFile->addFixableError($error, $i, 'Indent', $data);
                        if ($fix) {
                            $padding = str_repeat(' ', $expectedIndent);
                            if ($foundIndent === 0) {
                                $phpcsFile->fixer->addContentBefore($i, $padding);
                            } elseif ($tokens[$i]['code'] === T_COMMENT) {
                                $comment = $padding.ltrim($tokens[$i]['content']);
                                $phpcsFile->fixer->replaceToken($i, $comment);
                            } else {
                                $phpcsFile->fixer->replaceToken($i, $padding);
                            }
                        }
                    }//end if
                } else {
                    $nextCode = $i;
                }//end if

                if (!$inArg) {
                    $argStart = $nextCode;
                    $argEnd   = $phpcsFile->findEndOfStatement($nextCode);
                }
            }//end if

            // If we are within an argument we should be ignoring commas
            // as these are not signaling the end of an argument.
            if (!$inArg && $tokens[$i]['code'] === T_COMMA) {
                $next = $phpcsFile->findNext(array(T_WHITESPACE, T_COMMENT), ($i + 1), $closeBracket, true);
                if ($next === false) {
                    continue;
                }

                // Comma has to be the last token on the line.
                if (!$this->allowMultipleArguments && $tokens[$i]['line'] === $tokens[$next]['line']) {
                    $error = 'Only one argument is allowed per line in a multi-line function call';
                    $fix   = $phpcsFile->addFixableError($error, $next, 'MultipleArguments');
                    if ($fix) {
                        $phpcsFile->fixer->beginChangeset();
                        for ($x = ($next - 1); $x > $i; $x--) {
                            if ($tokens[$x]['code'] !== T_WHITESPACE) {
                                break;
                            }

                            $phpcsFile->fixer->replaceToken($x, '');
                        }

                        $phpcsFile->fixer->addContentBefore(
                            $next,
                            $phpcsFile->eolChar.str_repeat(' ', ($functionIndent + $this->indent))
                        );
                        $phpcsFile->fixer->endChangeset();
                    }
                }//end if

                $argStart = $next;
                $argEnd   = $phpcsFile->findEndOfStatement($next);
            }//end if
        }//end for

    }//end processMultiLineCall()


}//end class
