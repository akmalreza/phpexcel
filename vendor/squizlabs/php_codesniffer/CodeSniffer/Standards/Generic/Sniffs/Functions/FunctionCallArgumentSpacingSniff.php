<?php
/**
 * Generic_Sniffs_Functions_FunctionCallArgumentSpacingSniff.
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
 * Generic_Sniffs_Functions_FunctionCallArgumentSpacingSniff.
 *
 * Checks that calls to methods and functions are spaced correctly.
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
class Generic_Sniffs_Functions_FunctionCallArgumentSpacingSniff implements PHP_CodeSniffer_Sniff
{


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        $tokens = PHP_CodeSniffer_Tokens::$functionNameTokens;

        // For calling closures.
        $tokens[] = T_VARIABLE;

        return $tokens;

    }//end register()


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token in the
     *                                        stack passed in $tokens.
     *
     * @return void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Skip tokens that are the names of functions or classes
        // within their definitions. For example:
        // function myFunction...
        // "myFunction" is T_STRING but we should skip because it is not a
        // function or method *call*.
        $functionName    = $stackPtr;
        $ignoreTokens    = PHP_CodeSniffer_Tokens::$emptyTokens;
        $ignoreTokens[]  = T_BITWISE_AND;
        $functionKeyword = $phpcsFile->findPrevious($ignoreTokens, ($stackPtr - 1), null, true);
        if ($tokens[$functionKeyword]['code'] === T_FUNCTION || $tokens[$functionKeyword]['code'] === T_CLASS) {
            return;
        }

        // If the next non-whitespace token after the function or method call
        // is not an opening parenthesis then it cant really be a *call*.
        $openBracket = $phpcsFile->findNext(PHP_CodeSniffer_Tokens::$emptyTokens, ($functionName + 1), null, true);
        if ($tokens[$openBracket]['code'] !== T_OPEN_PARENTHESIS) {
            return;
        }

        if (!isset($tokens[$openBracket]['parenthesis_closer'])) {
            return;
        }

        $closeBracket  = $tokens[$openBracket]['parenthesis_closer'];
        $nextSeparator = $openBracket;

        $find = array(
                 T_COMMA,
                 T_VARIABLE,
                 T_CLOSURE,
                 T_OPEN_SHORT_ARRAY,
                );

        while (($nextSeparator = $phpcsFile->findNext($find, ($nextSeparator + 1), $closeBracket)) !== false) {
            if ($tokens[$nextSeparator]['code'] === T_CLOSURE) {
                // Skip closures.
                $nextSeparator = $tokens[$nextSeparator]['scope_closer'];
                continue;
            } elseif ($tokens[$nextSeparator]['code'] === T_OPEN_SHORT_ARRAY) {
                // Skips arrays using short notation.
                $nextSeparator = $tokens[$nextSeparator]['bracket_closer'];
                continue;
            }

            // Make sure the comma or variable belongs directly to this function call,
            // and is not inside a nested function call or array.
            $brackets    = $tokens[$nextSeparator]['nested_parenthesis'];
            $lastBracket = array_pop($brackets);
            if ($lastBracket !== $closeBracket) {
                continue;
            }

            if ($tokens[$nextSeparator]['code'] === T_COMMA) {
                if ($tokens[($nextSeparator - 1)]['code'] === T_WHITESPACE) {
                    $prev = $phpcsFile->findPrevious(PHP_CodeSniffer_Tokens::$emptyTokens, ($nextSeparator - 2), null, true);
                    if (!isset(PHP_CodeSniffer_Tokens::$heredocTokens[$tokens[$prev]['code']])) {
                        $error = 'Space found before comma in function call';
                        $fix   = $phpcsFile->addFixableError($error, $nextSeparator, 'SpaceBeforeComma');
                        if ($fix) {
                            $phpcsFile->fixer->replaceToken(($nextSeparator - 1), '');
                        }
                    }
                }

                if ($tokens[($nextSeparator + 1)]['code'] !== T_WHITESPACE) {
                    $error = 'No space found after comma in function call';
                    $fix   = $phpcsFile->addFixableError($error, $nextSeparator, 'NoSpaceAfterComma');
                    if ($fix) {
                        $phpcsFile->fixer->addContent($nextSeparator, ' ');
                    }
                } else {
                    // If there is a newline in the space, then they must be formatting
                    // each argument on a newline, which is valid, so ignore it.
                    $next = $phpcsFile->findNext(PHP_CodeSniffer_Tokens::$emptyTokens, ($nextSeparator + 1), null, true);
                    if ($tokens[$next]['line'] === $tokens[$nextSeparator]['line']) {
                        $space = strlen($tokens[($nextSeparator + 1)]['content']);
                        if ($space > 1) {
                            $error = 'Expected 1 space after comma in function call; %s found';
                            $data  = array($space);
                            $fix   = $phpcsFile->addFixableError($error, $nextSeparator, 'TooMuchSpaceAfterComma', $data);
                            if ($fix) {
                                $phpcsFile->fixer->replaceToken(($nextSeparator + 1), ' ');
                            }
                        }
                    }
                }//end if
            } else {
                // Token is a variable.
                $nextToken = $phpcsFile->findNext(PHP_CodeSniffer_Tokens::$emptyTokens, ($nextSeparator + 1), $closeBracket, true);
                if ($nextToken !== false && $tokens[$nextToken]['code'] === T_EQUAL) {
                    if (($tokens[($nextToken - 1)]['code']) !== T_WHITESPACE) {
                        $error = 'Expected 1 space before = sign of default value';
                        $fix   = $phpcsFile->addFixableError($error, $nextToken, 'NoSpaceBeforeEquals');
                        if ($fix) {
                            $phpcsFile->fixer->addContentBefore($nextToken, ' ');
                        }
                    }
                    if ($tokens[($nextToken + 1)]['code'] !== T_WHITESPACE) {
                        $error = 'Expected 1 space after = sign of default value';
                        $fix   = $phpcsFile->addFixableError($error, $nextToken, 'NoSpaceAfterEquals');
                        if ($fix) {
                            $phpcsFile->fixer->addContent($nextToken, ' ');
                        }
                    }
                }
            }//end if
        }//end while

    }//end process()


}//end class
