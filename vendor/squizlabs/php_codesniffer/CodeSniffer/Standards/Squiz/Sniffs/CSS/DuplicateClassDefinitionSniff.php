<?php
/**
 * Squiz_Sniffs_CSS_DuplicateClassDefinitionSniff.
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
 * Squiz_Sniffs_CSS_DuplicateClassDefinitionSniff.
 *
 * Check for duplicate class definitions that can be merged into one.
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class Squiz_Sniffs_CSS_DuplicateClassDefinitionSniff implements PHP_CodeSniffer_Sniff
{

    /**
     * A list of tokenizers this sniff supports.
     */
    public array $supportedTokenizers = array('CSS');


    /**
     * Returns the token types that this sniff is interested in.
     *
     * @return int[]
     */
    public function register()
    {
        return array(T_OPEN_TAG);

    }//end register()


    /**
     * Processes the tokens that this sniff is interested in.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file where the token was found.
     * @param int                  $stackPtr  The position in the stack where
     *                                        the token was found.
     *
     * @return void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Find the content of each class definition name.
        $classNames = array();
        $next       = $phpcsFile->findNext(T_OPEN_CURLY_BRACKET, ($stackPtr + 1));
        if ($next === false) {
            // No class definitions in the file.
            return;
        }

        // Save the class names in a "scope",
        // to prevent false positives with @media blocks.
        $scope = 'main';

        $find = array(
                 T_CLOSE_CURLY_BRACKET,
                 T_OPEN_CURLY_BRACKET,
                 T_COMMENT,
                 T_OPEN_TAG,
                );

        while ($next !== false) {
            $prev = $phpcsFile->findPrevious($find, ($next - 1));

            // Check if an inner block was closed.
            $beforePrev = $phpcsFile->findPrevious(PHP_CodeSniffer_Tokens::$emptyTokens, ($prev - 1), null, true);
            if ($beforePrev !== false
                && $tokens[$beforePrev]['code'] === T_CLOSE_CURLY_BRACKET
            ) {
                $scope = 'main';
            }

            // Create a sorted name for the class so we can compare classes
            // even when the individual names are all over the place.
            $name = '';
            for ($i = ($prev + 1); $i < $next; $i++) {
                $name .= $tokens[$i]['content'];
            }

            $name = trim($name);
            $name = str_replace("\n", ' ', $name);
            $name = preg_replace('|[\s]+|', ' ', $name);
            $name = str_replace(', ', ',', $name);

            $names = explode(',', $name);
            sort($names);
            $name = implode(',', $names);

            if ($name[0] === '@') {
                // Media block has its own "scope".
                $scope = $name;
            } elseif (isset($classNames[$scope][$name])) {
                $first = $classNames[$scope][$name];
                $error = 'Duplicate class definition found; first defined on line %s';
                $data  = array($tokens[$first]['line']);
                $phpcsFile->addError($error, $next, 'Found', $data);
            } else {
                $classNames[$scope][$name] = $next;
            }

            $next = $phpcsFile->findNext(T_OPEN_CURLY_BRACKET, ($next + 1));
        }//end while

    }//end process()


}//end class
