<?php

declare(strict_types=1);

namespace ChamberOrchestra\OpenApiDocBundle\Utils;

use PhpToken;

class ClassParser
{
    /**
     * Returns namespaced class in file and null if there is no class
     *
     * @param string $file
     * @return string|null
     */
    public function extractClass(string $file): ?string
    {
        $namespace = '';
        $tokens = PhpToken::tokenize(file_get_contents($file));
        $tokenCount = count($tokens);

        for ($i = 0; $i < $tokenCount; $i++) {
            if ($tokens[$i]->getTokenName() === 'T_NAMESPACE') {
                for ($j = $i + 1; $j < $tokenCount; $j++) {
                    if ($tokens[$j]->getTokenName() === 'T_NAME_QUALIFIED' || $tokens[$j]->getTokenName() === 'T_STRING') {
                        $namespace = $tokens[$j]->text;
                        break;
                    }
                }
            }

            if ($tokens[$i]->getTokenName() === 'T_CLASS' && $tokens[$i - 1]->getTokenName() !== 'T_DOUBLE_COLON') {
                for ($j = $i + 1; $j < $tokenCount; $j++) {
                    if ($tokens[$j]->getTokenName() === 'T_WHITESPACE') {
                        continue;
                    }

                    if ($tokens[$j]->getTokenName() === 'T_STRING') {
                        return $namespace.'\\'.$tokens[$j]->text;
                    }

                    break;
                }
            }
        }

        return null;
    }
}