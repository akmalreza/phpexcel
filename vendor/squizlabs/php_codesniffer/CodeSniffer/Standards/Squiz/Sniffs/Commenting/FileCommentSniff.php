<?php
/**
 * Parses and verifies the file doc comment.
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
 * Parses and verifies the file doc comment.
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

class Squiz_Sniffs_Commenting_FileCommentSniff implements PHP_CodeSniffer_Sniff
{

    public \PHP_CodeSniffer_File $currentFile;
    /**
     * A list of tokenizers this sniff supports.
     */
    public array $supportedTokenizers = array(
                                   'PHP',
                                   'JS',
                                  );


    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return array(T_OPEN_TAG);

    }//end register()


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return int
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $this->currentFile = $phpcsFile;

        $tokens       = $phpcsFile->getTokens();
        $commentStart = $phpcsFile->findNext(T_WHITESPACE, ($stackPtr + 1), null, true);

        if ($tokens[$commentStart]['code'] === T_COMMENT) {
            $phpcsFile->addError('You must use "/**" style comments for a file comment', $commentStart, 'WrongStyle');
            $phpcsFile->recordMetric($stackPtr, 'File has doc comment', 'yes');
            return ($phpcsFile->numTokens + 1);
        } elseif ($commentStart === false || $tokens[$commentStart]['code'] !== T_DOC_COMMENT_OPEN_TAG) {
            $phpcsFile->addError('Missing file doc comment', $stackPtr, 'Missing');
            $phpcsFile->recordMetric($stackPtr, 'File has doc comment', 'no');
            return ($phpcsFile->numTokens + 1);
        }

        $commentEnd = $tokens[$commentStart]['comment_closer'];

        $nextToken = $phpcsFile->findNext(
            T_WHITESPACE,
            ($commentEnd + 1),
            null,
            true
        );

        $ignore = array(
                   T_CLASS,
                   T_INTERFACE,
                   T_TRAIT,
                   T_FUNCTION,
                   T_CLOSURE,
                   T_PUBLIC,
                   T_PRIVATE,
                   T_PROTECTED,
                   T_FINAL,
                   T_STATIC,
                   T_ABSTRACT,
                   T_CONST,
                   T_PROPERTY,
                   T_INCLUDE,
                   T_INCLUDE_ONCE,
                   T_REQUIRE,
                   T_REQUIRE_ONCE,
                  );

        if (in_array($tokens[$nextToken]['code'], $ignore)) {
            $phpcsFile->addError('Missing file doc comment', $stackPtr, 'Missing');
            $phpcsFile->recordMetric($stackPtr, 'File has doc comment', 'no');
            return ($phpcsFile->numTokens + 1);
        }

        $phpcsFile->recordMetric($stackPtr, 'File has doc comment', 'yes');

        // No blank line between the open tag and the file comment.
        if ($tokens[$commentStart]['line'] > ($tokens[$stackPtr]['line'] + 1)) {
            $error = 'There must be no blank lines before the file comment';
            $phpcsFile->addError($error, $stackPtr, 'SpacingAfterOpen');
        }

        // Exactly one blank line after the file comment.
        $next = $phpcsFile->findNext(T_WHITESPACE, ($commentEnd + 1), null, true);
        if ($tokens[$next]['line'] !== ($tokens[$commentEnd]['line'] + 2)) {
            $error = 'There must be exactly one blank line after the file comment';
            $phpcsFile->addError($error, $commentEnd, 'SpacingAfterComment');
        }

        // Required tags in correct order.
        $required = array(
                     '@package'    => true,
                     '@subpackage' => true,
                     '@author'     => true,
                     '@copyright'  => true,
                    );

        $foundTags = array();
        foreach ($tokens[$commentStart]['comment_tags'] as $tag) {
            $name       = $tokens[$tag]['content'];
            $isRequired = isset($required[$name]);

            if ($isRequired && in_array($name, $foundTags)) {
                $error = 'Only one %s tag is allowed in a file comment';
                $data  = array($name);
                $phpcsFile->addError($error, $tag, 'Duplicate'.ucfirst(substr($name, 1)).'Tag', $data);
            }

            $foundTags[] = $name;

            if (!$isRequired) {
                continue;
            }

            $string = $phpcsFile->findNext(T_DOC_COMMENT_STRING, $tag, $commentEnd);
            if ($string === false || $tokens[$string]['line'] !== $tokens[$tag]['line']) {
                $error = 'Content missing for %s tag in file comment';
                $data  = array($name);
                $phpcsFile->addError($error, $tag, 'Empty'.ucfirst(substr($name, 1)).'Tag', $data);
                continue;
            }

            if ($name === '@author') {
                if ($tokens[$string]['content'] !== 'Squiz Pty Ltd <products@squiz.net>') {
                    $error = 'Expected "Squiz Pty Ltd <products@squiz.net>" for author tag';
                    $fix   = $phpcsFile->addFixableError($error, $tag, 'IncorrectAuthor');
                    if ($fix) {
                        $expected = 'Squiz Pty Ltd <products@squiz.net>';
                        $phpcsFile->fixer->replaceToken($string, $expected);
                    }
                }
            } elseif ($name === '@copyright') {
                if (preg_match('/^(\d{4})(-\d{4})? (Squiz Pty Ltd \(ABN 77 084 670 600\))$/', $tokens[$string]['content']) === 0) {
                    $error = 'Expected "xxxx-xxxx Squiz Pty Ltd (ABN 77 084 670 600)" for copyright declaration';
                    $fix   = $phpcsFile->addFixableError($error, $tag, 'IncorrectCopyright');
                    if ($fix) {
                        $matches = array();
                        preg_match('/^((\d{4})(-\d{4})?)?.*$/', $tokens[$string]['content'], $matches);
                        if (!isset($matches[1])) {
                            $matches[1] = date('Y');
                        }

                        $expected = $matches[1].' Squiz Pty Ltd (ABN 77 084 670 600)';
                        $phpcsFile->fixer->replaceToken($string, $expected);
                    }
                }
            }//end if
        }//end foreach

        // Check if the tags are in the correct position.
        $pos = 0;
        foreach (array_keys($required) as $tag) {
            if (!in_array($tag, $foundTags)) {
                $error = 'Missing %s tag in file comment';
                $data  = array($tag);
                $phpcsFile->addError($error, $commentEnd, 'Missing'.ucfirst(substr($tag, 1)).'Tag', $data);
            }

            if (!isset($foundTags[$pos])) {
                break;
            }

            if ($foundTags[$pos] !== $tag) {
                $error = 'The tag in position %s should be the %s tag';
                $data  = array(
                          ($pos + 1),
                          $tag,
                         );
                $phpcsFile->addError($error, $tokens[$commentStart]['comment_tags'][$pos], ucfirst(substr($tag, 1)).'TagOrder', $data);
            }

            $pos++;
        }//end foreach

        // Ignore the rest of the file.
        return ($phpcsFile->numTokens + 1);

    }//end process()


}//end class
