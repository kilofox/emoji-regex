<?php

/**
 * Emoji regex generator.
 *
 * @author Tinsh <kilofox2000@gmail.com>
 * @copyright (c) 2020 Kilofox Studio
 */
class EmojiRegexGenerator
{
    /** @var string Emoji data */
    private $data = '';

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        /**
         * @see https://unicode.org/Public/emoji/13.1/emoji-sequences.txt
         */
        $this->data = file_get_contents('emoji-sequences.txt');
    }

    /**
     * @param string $firstChar
     * @param string $secondChar
     * @return bool
     */
    private static function isAdjacent($firstChar, $secondChar)
    {
        return bin2hex($firstChar) + 1 == bin2hex($secondChar);
    }

    /**
     * @param int $key
     * @param string $char
     * @param array $data
     * @param array $rangeData
     * @return int
     */
    private function findStartKey($key, $char, $data, $rangeData = [])
    {
        $prevKey = $key - 1;

        if (!empty($data[$prevKey])) {
            if (self::isAdjacent($data[$prevKey], $char)) {
                return $this->findStartKey($prevKey, $data[$prevKey], $data);
            }
        }

        if (!empty($rangeData[$prevKey])) {
            $pos = strpos($rangeData[$prevKey], '..');
            $prevFrom = substr($rangeData[$prevKey], 0, $pos);
            $prevTo = substr($rangeData[$prevKey], $pos + 2);

            if (self::isAdjacent($prevTo, $char)) {
                return $this->findStartKey($prevKey, $prevFrom, $data, $rangeData);
            }
        }

        return $key;
    }

    /**
     * @param int $key
     * @param string $char
     * @param array $data
     * @param array $rangeData
     * @return int
     */
    private function findEndKey($key, $char, $data, $rangeData = [])
    {
        $nextKey = $key + 1;

        if (!empty($data[$nextKey])) {
            if (self::isAdjacent($char, $data[$nextKey])) {
                return $this->findEndKey($nextKey, $data[$nextKey], $data, $rangeData);
            }
        }

        if (!empty($rangeData[$nextKey])) {
            $pos = strpos($rangeData[$nextKey], '..');
            $nextFrom = substr($rangeData[$nextKey], 0, $pos);
            $nextTo = substr($rangeData[$nextKey], $pos + 2);

            if (self::isAdjacent($char, $nextFrom)) {
                return $this->findEndKey($nextKey, $nextTo, $data, $rangeData);
            }
        }

        return $key;
    }

    /**
     * Basic emoji set.
     *
     * @return string
     */
    public function getBasicEmojiSet()
    {
        preg_match_all('/(?:([0-F]{4,5})|([0-F]{4,5}\.{2}[0-F]{4,5})|([0-F]{4,5})\040FE0F)\040+;\040Basic_Emoji/', $this->data, $matches);

        $codePoints = [];
        $characters = [];
        $skipKey = 0;

        foreach ($matches[1] as $key => $node) {
            if (!$node || $key < $skipKey) {
                continue;
            }

            $startKey = $this->findStartKey($key, $node, $matches[1], $matches[2]);
            $endKey = $this->findEndKey($key, $node, $matches[1], $matches[2]);

            if (!empty($matches[1][$startKey])) {
                $startChar = $matches[1][$startKey];
            } else {
                $startChar = substr($matches[2][$startKey], 0, strpos($matches[2][$startKey], '..'));
            }

            if (!empty($matches[1][$endKey])) {
                $endChar = $matches[1][$endKey];
            } else {
                $endChar = substr($matches[2][$endKey], strpos($matches[2][$endKey], '..') + 2);
            }

            if ($startChar === $endChar) {
                $characters[] = '\x{' . $startChar . '}';
            } elseif (self::isAdjacent($startChar, $endChar)) {
                $characters[] = '\x{' . $startChar . '}\x{' . $endChar . '}';
            } else {
                $characters[] = '\x{' . $startChar . '}-\x{' . $endChar . '}';
            }

            for ($i = $startKey; $i <= $endKey; $i++) {
                unset($matches[2][$i]);
            }

            $skipKey = $endKey + 1;
        }

        if ($characters) {
            $codePoints[] = '[' . implode('', $characters) . ']';
        }

        $characters = [];
        $skipKey = 0;

        foreach ($matches[2] as $key => $node) {
            if (!$node || $key < $skipKey) {
                continue;
            }

            $pos = strpos($node, '..');
            $from = substr($node, 0, $pos);
            $to = substr($node, $pos + 2);

            $startKey = $this->findStartKey($key, $from, [], $matches[2]);
            $endKey = $this->findEndKey($key, $to, [], $matches[2]);

            $startChar = substr($matches[2][$startKey], 0, strpos($matches[2][$startKey], '..'));
            $endChar = substr($matches[2][$endKey], strpos($matches[2][$endKey], '..') + 2);

            if ($startChar === $endChar) {
                $characters[] = '\x{' . $startChar . '}';
            } elseif (self::isAdjacent($startChar, $endChar)) {
                $characters[] = '\x{' . $startChar . '}\x{' . $endChar . '}';
            } else {
                $characters[] = '\x{' . $startChar . '}-\x{' . $endChar . '}';
            }

            $skipKey = $endKey + 1;
        }

        if ($characters) {
            $codePoints[] = '[' . implode('', $characters) . ']';
        }

        $characters = [];
        $skipKey = 0;

        foreach ($matches[3] as $key => $node) {
            if (!$node || $key < $skipKey) {
                continue;
            }

            $startKey = $this->findStartKey($key, $node, $matches[3]);
            $endKey = $this->findEndKey($key, $node, $matches[3]);
            $startChar = $matches[3][$startKey];
            $endChar = $matches[3][$endKey];

            if ($startChar === $endChar) {
                $characters[] = '\x{' . $startChar . '}';
            } elseif (self::isAdjacent($startChar, $endChar)) {
                $characters[] = '\x{' . $startChar . '}\x{' . $endChar . '}';
            } else {
                $characters[] = '\x{' . $startChar . '}-\x{' . $endChar . '}';
            }

            $skipKey = $endKey + 1;
        }

        if ($characters) {
            $codePoints[] = '[' . implode('', $characters) . ']\x{FE0F}';
        }

        return implode('|', $codePoints);
    }

    /**
     * Emoji keycap sequence set.
     *
     * @return string
     */
    public function getKeycapSequenceSet()
    {
        preg_match_all('/(00[23][0-A])\040FE0F\04020E3;\040Emoji_Keycap_Sequence/', $this->data, $matches);

        $codePoints = [];
        $characters = [];
        $skipKey = 0;

        foreach ($matches[1] as $key => $node) {
            if (!$node || $key < $skipKey) {
                continue;
            }

            $startKey = $this->findStartKey($key, $node, $matches[1]);
            $endKey = $this->findEndKey($key, $node, $matches[1]);
            $startChar = $matches[1][$startKey];
            $endChar = $matches[1][$endKey];

            if ($startChar === $endChar) {
                $characters[] = '\x{' . $startChar . '}';
            } elseif (self::isAdjacent($startChar, $endChar)) {
                $characters[] = '\x{' . $startChar . '}\x{' . $endChar . '}';
            } else {
                $characters[] = '\x{' . $startChar . '}-\x{' . $endChar . '}';
            }

            $skipKey = $endKey + 1;
        }

        if ($characters) {
            $codePoints[] = '[' . implode('', $characters) . ']\x{FE0F}\x{20E3}';
        }

        return implode('|', $codePoints);
    }

    /**
     * RGI emoji flag sequence set.
     *
     * @return string
     */
    public function getFlagSequenceSet()
    {
        preg_match_all('/(1F1[EF][0-F])\040(1F1[EF][0-F])\040+;\040RGI_Emoji_Flag_Sequence/', $this->data, $matches);

        $codePoints = [];
        $groups = [];

        foreach ($matches[2] as $key => $node) {
            $groups[$matches[1][$key]][] = $node;
        }

        foreach ($groups as $firstChar => $secondChars) {
            $characters = [];
            $skipKey = 0;

            foreach ($secondChars as $key => $node) {
                if (!$node || $key < $skipKey) {
                    continue;
                }

                $startKey = $this->findStartKey($key, $node, $secondChars);
                $endKey = $this->findEndKey($key, $node, $secondChars);
                $startChar = $secondChars[$startKey];
                $endChar = $secondChars[$endKey];

                if ($startChar === $endChar) {
                    $characters[] = '\x{' . $startChar . '}';
                } elseif (self::isAdjacent($startChar, $endChar)) {
                    $characters[] = '\x{' . $startChar . '}\x{' . $endChar . '}';
                } else {
                    $characters[] = '\x{' . $startChar . '}-\x{' . $endChar . '}';
                }

                $skipKey = $endKey + 1;
            }

            $codePoints[] = '\x{' . $firstChar . '}[' . implode('', $characters) . ']';
        }

        return implode('|', $codePoints);
    }

    /**
     * RGI emoji tag sequence set.
     *
     * @return string
     */
    public function getTagSequenceSet()
    {
        preg_match_all('/1F3F4\040E0067\040E0062\040(E00[0-9]{2})\040(E00[0-F]{2})\040(E00[0-9]{2})\040E007F;\040RGI_Emoji_Tag_Sequence/', $this->data, $matches);

        $codePoints = [];

        foreach ($matches[1] as $key => $node) {
            $codePoints[] = '\x{1F3F4}\x{E0067}\x{E0062}\x{' . $node . '}\x{' . $matches[2][$key] . '}\x{' . $matches[3][$key] . '}\x{E007F}';
        }

        return implode('|', $codePoints);
    }

    /**
     * RGI emoji modifier sequence set.
     *
     * @return string
     */
    public function getModifierSequenceSet()
    {
        preg_match_all('/(2[67][0-F]{2}|1F[3-9][0-F]{2})\040(1F3F[B-F])\040+;\040RGI_Emoji_Modifier_Sequence/', $this->data, $matches);

        $codePoints = [];
        $groups = [];

        foreach ($matches[2] as $key => $node) {
            $groups[$matches[1][$key]][] = $node;
        }

        foreach ($groups as $firstChar => $secondChars) {
            $characters = [];
            $skipKey = 0;

            foreach ($secondChars as $key => $node) {
                if (!$node || $key < $skipKey) {
                    continue;
                }

                $startKey = $this->findStartKey($key, $node, $secondChars);
                $endKey = $this->findEndKey($key, $node, $secondChars);
                $startChar = $secondChars[$startKey];
                $endChar = $secondChars[$endKey];

                if ($startChar === $endChar) {
                    $characters[] = '\x{' . $startChar . '}';
                } elseif (self::isAdjacent($startChar, $endChar)) {
                    $characters[] = '\x{' . $startChar . '}\x{' . $endChar . '}';
                } else {
                    $characters[] = '\x{' . $startChar . '}-\x{' . $endChar . '}';
                }

                $skipKey = $endKey + 1;
            }

            $codePoints[] = '\x{' . $firstChar . '}[' . implode('', $characters) . ']';
        }

        return implode('|', $codePoints);
    }

    /**
     * Generate emoji regex.
     *
     * @return string
     */
    public function generate()
    {
        $basicEmojiSet = $this->getBasicEmojiSet();
        $keycapSequenceSet = $this->getKeycapSequenceSet();
        $flagSequenceSet = $this->getFlagSequenceSet();
        $tagSequenceSet = $this->getTagSequenceSet();
        $modifierSequenceSet = $this->getModifierSequenceSet();

        $flagSequenceSet and $emojiSets[] = $flagSequenceSet;
        $modifierSequenceSet and $emojiSets[] = $modifierSequenceSet;
        $keycapSequenceSet and $emojiSets[] = $keycapSequenceSet;
        $tagSequenceSet and $emojiSets[] = $tagSequenceSet;
        $basicEmojiSet and $emojiSets[] = $basicEmojiSet;

        return '/' . implode('|', $emojiSets) . '/u';
    }

}

echo (new EmojiRegexGenerator)->generate();
