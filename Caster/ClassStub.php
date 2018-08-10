<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarDumper\Caster;

use Symfony\Component\VarDumper\Cloner\Stub;

/**
 * Represents a PHP class identifier.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class ClassStub extends ConstStub
{
    /**
     * @param string   A PHP identifier, e.g. a class, method, interface, etc. name
     * @param callable The callable targeted by the identifier when it is ambiguous or not a real PHP identifier
     */
    public function __construct(string $identifier, $callable = null)
    {
        $this->value = $identifier;

        if (0 < $i = strrpos($identifier, '\\')) {
            $this->attr['ellipsis'] = \strlen($identifier) - $i;
            $this->attr['ellipsis-type'] = 'class';
            $this->attr['ellipsis-tail'] = 1;
        }

        try {
            if (null !== $callable) {
                if ($callable instanceof \Closure) {
                    $r = new \ReflectionFunction($callable);
                } elseif (\is_object($callable)) {
                    $r = array($callable, '__invoke');
                } elseif (\is_array($callable)) {
                    $r = $callable;
                } elseif (false !== $i = strpos($callable, '::')) {
                    $r = array(substr($callable, 0, $i), substr($callable, 2 + $i));
                } else {
                    $r = new \ReflectionFunction($callable);
                }
            } elseif (0 < $i = strpos($identifier, '::') ?: strpos($identifier, '->')) {
                $r = array(substr($identifier, 0, $i), substr($identifier, 2 + $i));
            } else {
                $r = new \ReflectionClass($identifier);
            }

            if (\is_array($r)) {
                if (false === strpos($r[1], '{closure}')) {
                    try {
                        $r = new \ReflectionMethod($r[0], $r[1]);
                    } catch (\ReflectionException $e) {
                        $r = new \ReflectionClass($r[0]);
                    }
                } else {
                    $r = new \ReflectionClass($r[0]);
                }
            }

            if (null !== $callable && $r instanceof \ReflectionFunctionAbstract) {
                $s = ReflectionCaster::castFunctionAbstract($r, array(), new Stub(), true);
                $s = ReflectionCaster::getSignature($s);

                if ('()' === substr($identifier, -2)) {
                    $this->value = substr_replace($identifier, $s, -2);
                } else {
                    $this->value .= $s;
                }
                if (isset($this->attr['ellipsis'])) {
                    $this->attr['ellipsis'] += \strlen($this->value) - \strlen($identifier);
                }
            }
        } catch (\ReflectionException $e) {
            return;
        }

        if ($f = $r->getFileName()) {
            $this->attr['file'] = $f;
            $this->attr['line'] = $r->getStartLine();
        }
    }

    public static function wrapCallable($callable)
    {
        if (\is_object($callable) || !\is_callable($callable)) {
            return $callable;
        }

        if (!\is_array($callable)) {
            $callable = new static($callable, $callable);
        } elseif (\is_string($callable[0])) {
            $callable[0] = new static($callable[0], $callable);
        } else {
            $callable[1] = new static($callable[1], $callable);
        }

        return $callable;
    }
}
