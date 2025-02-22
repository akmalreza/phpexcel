<?php
/**
 * Squiz_Sniffs_Strings_ConcatenationSpacingSniff.
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
 * Squiz_Sniffs_Strings_ConcatenationSpacingSniff.
 *
 * Makes sure there are no spaces between the concatenation operator (.) and
 * the strings being concatenated.
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
class Squiz_Sniffs_Strings_ConcatenationSpacingSniff implements PHP_CodeSniffer_Sniff
{

    /**
     * The number of spaces before and after a string concat.
     */
    public int $spacing = 0;

    /**
     * Allow newlines instead of spaces.
     */
    public bool $ignoreNewlines = false;


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return array(T_STRING_CONCAT);

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
        $this->spacing = (int) $this->spacing;

        $tokens = $phpcsFile->getTokens();
        if ($tokens[($stackPtr - 1)]['code'] !== T_WHITESPACE) {
            $before = 0;
        } elseif ($tokens[($stackPtr - 2)]['line'] !== $tokens[$stackPtr]['line']) {
            $before = 'newline';
        } else {
            $before = $tokens[($stackPtr - 1)]['length'];
        }

        if ($tokens[($stackPtr + 1)]['code'] !== T_WHITESPACE) {
            $after = 0;
        } elseif ($tokens[($stackPtr + 2)]['line'] !== $tokens[$stackPtr]['line']) {
            $after = 'newline';
        } else {
            $after = $tokens[($stackPtr + 1)]['length'];
        }

        $phpcsFile->recordMetric($stackPtr, 'Spacing before string concat', $before);
        $phpcsFile->recordMetric($stackPtr, 'Spacing after string concat', $after);

        if (($before === $this->spacing || ($before === 'newline' && $this->ignoreNewlines))
            && ($after === $this->spacing || ($after === 'newline' && $this->ignoreNewlines))
        ) {
            return;
        }

        if ($this->spacing === 0) {
            $message = 'Concat operator must not be surrounded by spaces';
            $data    = array();
        } else {
            if ($this->spacing > 1) {
                $message = 'Concat operator must be surrounded by %s spaces';
            } else {
                $message = 'Concat operator must be surrounded by a single space';
            }

            $data = array($this->spacing);
        }

        $fix = $phpcsFile->addFixableError($message, $stackPtr, 'PaddingFound', $data);

        if ($fix) {
            $padding = str_repeat(' ', $this->spacing);
            if ($before !== 'newline' || !$this->ignoreNewlines) {
                if ($tokens[($stackPtr - 1)]['code'] === T_WHITESPACE) {
                    $phpcsFile->fixer->beginChangeset();
                    $phpcsFile->fixer->replaceToken(($stackPtr - 1), $padding);
                    if ($this->spacing === 0
                        && ($tokens[($stackPtr - 2)]['code'] === T_LNUMBER
                        || $tokens[($stackPtr - 2)]['code'] === T_DNUMBER)
                    ) {
                        $phpcsFile->fixer->replaceToken(($stackPtr - 2), '('.$tokens[($stackPtr - 2)]['content'].')');
                    }
                    $phpcsFile->fixer->endChangeset();
                } elseif ($this->spacing > 0) {
                    $phpcsFile->fixer->addContent(($stackPtr - 1), $padding);
                }
            }

            if ($after !== 'newline' || !$this->ignoreNewlines) {
                if ($tokens[($stackPtr + 1)]['code'] === T_WHITESPACE) {
                    $phpcsFile->fixer->beginChangeset();
                    $phpcsFile->fixer->replaceToken(($stackPtr + 1), $padding);
                    if ($this->spacing === 0
                        && ($tokens[($stackPtr + 2)]['code'] === T_LNUMBER
                        || $tokens[($stackPtr + 2)]['code'] === T_DNUMBER)
                    ) {
                        $phpcsFile->fixer->replaceToken(($stackPtr + 2), '('.$tokens[($stackPtr + 2)]['content'].')');
                    }
                    $phpcsFile->fixer->endChangeset();
                } elseif ($this->spacing > 0) {
                    $phpcsFile->fixer->addContent($stackPtr, $padding);
                }
            }
        }//end if

    }//end process()


}//end class
