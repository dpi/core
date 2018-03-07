<?php

namespace Drupal\Component\Utility;

/**
 * Defines a class that can explode and implode tags.
 *
 * @ingroup utility
 */
class Tags {

  /**
   * Explodes a string of tags into an array.
   *
   * Best effort is made to get tags, errors will be placed into an array if
   * string is invalid.
   *
   * @param string $string
   *   A string to explode.
   * @param array $errors
   *   (optional) A reference to an array to fill with error messages. The array
   *   contains arrays with keys 'message' and 'arguments' suitable for
   *   translation.
   *
   * @return array
   *   An array of tags.
   */
  public static function explode($string, &$errors = NULL) {
    $errors = [];
    $tags = [];

    while (strlen($string) > 0) {
      preg_match('/^\s*(")/', $string, $matches, PREG_OFFSET_CAPTURE);
      $in_quotes = count($matches) != 0;
      if ($in_quotes) {
        $first_quote_position = $matches[1][1];
        $string = substr($string, $first_quote_position + 1);

        // Find first single quote.
        preg_match('/(?:^|[^"])(?:"")*(")(?:[^"]|$)/', $string, $matches, PREG_OFFSET_CAPTURE);
        $end_quote_position = isset($matches[1][1]) ? $matches[1][1] : NULL;
        if (!isset($end_quote_position)) {
          $errors[] = [
            'message' => 'No ending quote character found.',
            'arguments' => [],
          ];
          break;
        }

        $tag = substr($string, 0, $end_quote_position);
        $tags[] = $tag;
        $string = substr($string, $end_quote_position + 1);

        // Next char should be whitespace then comma or end of string.
        preg_match('/^\s*([,]|$)/', $string, $matches, PREG_OFFSET_CAPTURE);
        $next_char = isset($matches[1][0]) ? $matches[1][0] : NULL;
        if ($next_char === ',') {
          // Take off the comma.
          $string = substr($string, 1);
        }
        elseif ($next_char === '') {
          // End of string. Finish.
          break;
        }
        else {
          $errors[] = [
            'message' => 'Unexpected text after "@tag". Expected comma or end of text. Found "@unexpected".',
            'arguments' => [
              '@tag' => $tag,
              '@unexpected' => substr($string, 0, 10),
            ],
          ];
          break;
        }
      }
      else {
        $end_position = strpos($string, ',');
        $end_position = $end_position !== FALSE ? $end_position : strlen($string);
        $tag = substr($string, 0, $end_position);
        $string = substr($string, $end_position + 1);
        // Determine if there are any single quote characters.
        preg_match('/[^"](?:"")*(")(?:[^"]|$)/', $tag, $matches, PREG_OFFSET_CAPTURE);
        if (!count($matches)) {
          $tags[] = $tag;
        }
        else {
          $sample_end = $matches[1][1];
          $sample_start = max($sample_end - 10, 0);
          $string_to_quote = substr($tag, $sample_start, $sample_end);
          $errors[] = [
            'message' => 'Unexpected quote character found after "@tag"',
            'arguments' => [
              '@tag' => $string_to_quote,
            ],
          ];
          break;
        }
      }
    }

    // Replace escaped quotes with singles.
    $tags = array_map(
      function ($tag) {
        $tag = trim($tag);
        return str_replace('""', '"', $tag);
      },
      $tags
    );

    // Remove zero length.
    $tags = array_filter($tags);
    // Remove duplicates.
    $tags = array_unique($tags);
    // Reset keys.
    $tags = array_values($tags);

    return $tags;
  }

  /**
   * Encodes a tag string, taking care of special cases like commas and quotes.
   *
   * @param string $tag
   *   A tag string.
   *
   * @return string
   *   The encoded string.
   */
  public static function encode($tag) {
    if (strpos($tag, ',') !== FALSE || strpos($tag, '"') !== FALSE) {
      return '"' . str_replace('"', '""', $tag) . '"';
    }
    return $tag;
  }

  /**
   * Implodes an array of tags into a string.
   *
   * @param array $tags
   *   An array of tags.
   *
   * @return string
   *   The imploded string.
   */
  public static function implode($tags) {
    $encoded_tags = [];
    foreach ($tags as $tag) {
      $encoded_tags[] = self::encode($tag);
    }
    return implode(', ', $encoded_tags);
  }

}
