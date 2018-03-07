<?php

namespace Drupal\Tests\Core\Common;

use Drupal\Component\Utility\Tags;
use Drupal\Tests\UnitTestCase;

/**
 * Tests explosion and implosion of autocomplete tags.
 *
 * @group Common
 */
class TagsTest extends UnitTestCase {

  protected $validTags = [
    'Drupal' => 'Drupal',
    'Drupal with some spaces' => 'Drupal with some spaces',
    '"Legendary Drupal mascot of doom: ""Druplicon"""' => 'Legendary Drupal mascot of doom: "Druplicon"',
    '"Drupal, although it rhymes with sloopal, is as awesome as a troopal!"' => 'Drupal, although it rhymes with sloopal, is as awesome as a troopal!',
  ];

  /**
   * Explodes a series of tags.
   */
  public function explodeTags() {
    $string = implode(', ', array_keys($this->validTags));
    $tags = Tags::explode($string);
    $this->assertTags($tags);
  }

  /**
   * Implodes a series of tags.
   */
  public function testImplodeTags() {
    $tags = array_values($this->validTags);
    // Let's explode and implode to our heart's content.
    for ($i = 0; $i < 10; $i++) {
      $string = Tags::implode($tags);
      $tags = Tags::explode($string);
    }
    $this->assertTags($tags);
  }

  /**
   * Helper function: asserts that the ending array of tags is what we wanted.
   */
  protected function assertTags($tags) {
    $original = $this->validTags;
    foreach ($tags as $tag) {
      $key = array_search($tag, $original);
      $this->assertTrue((bool) $key, $tag, sprintf('Make sure tag %s shows up in the final tags array (originally %s)', $tag, $key));
      unset($original[$key]);
    }
    foreach ($original as $leftover) {
      $this->fail(sprintf('Leftover tag %s was left over.', $leftover));
    }
  }

  /**
   * Test converting a string to tags.
   *
   * @param string $string
   *   String to explode.
   * @param array $tagsExpected
   *   Expected result after explosion, or best effort if errors found.
   * @param array $expectedErrors
   *   The logged errors if any.
   *
   * @dataProvider providerTestSafeExplode
   */
  public function testSafeExplode($string, $tagsExpected, $expectedErrors = []) {
    $tags = Tags::safeExplode($string, $errors);
    $this->assertEquals($tagsExpected, $tags);
    $actualErrors = array_column($errors, 'message');
    $this->assertEquals($expectedErrors, $actualErrors);

    // Convert back to string to test explosion is compatible with implosion.
    $imploded = Tags::implode($tags);
    $this->assertEquals($tags, Tags::explode($imploded));
  }

  /**
   * Provides test data for ::testSafeExplode().
   *
   * @return array
   */
  public function providerTestSafeExplode() {
    $tests = [];

    $tests['unquoted'] = [
      'hello',
      ['hello'],
    ];
    $tests['unquoted multiword'] = [
      'Drupal with some spaces',
      ['Drupal with some spaces'],
    ];
    $tests['quoted, tag with comma'] = [
      '"Hello, World"',
      ['Hello, World'],
    ];
    $tests['quoted, missing trailing quote'] = [
      '"Hello',
      [],
      ['No ending quote character found.'],
    ];
    $tests['unquoted, unexpected quote'] = [
      'Hello"',
      [],
      ['Unexpected quote character found after "@tag"'],
    ];
    $tests['unquoted, empty tags'] = [
      ',,,,,,',
      [],
    ];
    $tests['unescaped, empty tags, word, empty tags'] = [
      ',,hello,,',
      ['hello'],
    ];
    $tests['quoted, empty'] = [
      '"hello",',
      ['hello'],
    ];
    $tests['unquoted, tag, empty tag'] = [
      'hello,',
      ['hello'],
    ];
    $tests['unquoted, quoted'] = [
      'unquoted,"quoted2"',
      ['unquoted', 'quoted2'],
    ];
    $tests['unquoted, unquoted'] = [
      'unquoted,unquoted2',
      ['unquoted', 'unquoted2'],
    ];
    $tests['quoted, unquoted'] = [
      '"quoted",unquoted',
      ['quoted', 'unquoted'],
    ];
    $tests['quoted, quoted'] = [
      '"quoted","quoted2"',
      ["quoted", "quoted2"],
    ];
    $tests['empty tag, unquoted'] = [
      ',hello',
      ['hello'],
    ];
    $tests['empty tag, quoted'] = [
      ',"hello"',
      ['hello'],
    ];
    $tests['quoted, unexpected quote'] = [
      '"Hello "Foo bar" World, baz"',
      ['Hello'],
      ['Unexpected text after "@tag". Expected comma or end of text. Found "@unexpected".'],
    ];
    $tests['quoted, spaces within quotes'] = [
      '"  hello  "',
      ['hello'],
    ];
    $tests['quoted, missing comma, unquoted'] = [
      '"Hello" World',
      ['Hello'],
      ['Unexpected text after "@tag". Expected comma or end of text. Found "@unexpected".'],
    ];
    $tests['quoted, missing comma, quoted'] = [
      '"Hello" "World"',
      ['Hello'],
      ['Unexpected text after "@tag". Expected comma or end of text. Found "@unexpected".'],
    ];
    $tests['unquoted, unexpected quote, word'] = [
      'Hello "Foo bar" World, baz',
      [],
      ['Unexpected quote character found after "@tag"'],
    ];
    $tests['Quoted with no contents, missing comma'] = [
      '""Hello "Foo bar" World, baz"',
      [],
      ['Unexpected text after "@tag". Expected comma or end of text. Found "@unexpected".'],
    ];
    $tests['unquoted, unquoted, trailing escaped'] = [
      'Hello Foo bar World, baz""',
      ['Hello Foo bar World', 'baz"'],
    ];
    $tests['unquoted, word within escaped, quoted, empty tag, unexpected character'] = [
      'Hello ""Foo bar"" World, ""baz""',
      ['Hello "Foo bar" World'],
      ['Unexpected text after "@tag". Expected comma or end of text. Found "@unexpected".'],
    ];
    $tests['unquoted word within escaped, unquoted'] = [
      'Hello ""Foo bar"" World, baz',
      ['Hello "Foo bar" World', 'baz'],
    ];
    $tests['quoted, words, escaped words, word'] = [
      '"Hello ""Foo bar"" World, baz"',
      ['Hello "Foo bar" World, baz'],
    ];
    // Two quotes should not get escaped, creates empty tag.
    $tests['quoted, empty, unquoted'] = [
      '"",hello',
      ['hello'],
    ];
    $tests['literal double quote, missing trailing quote'] = [
      '"""',
      [],
      ['No ending quote character found.'],
    ];
    $tests['quoted, starts with escaped'] = [
      '"""Hello"',
      ['"Hello'],
    ];
    $tests['quoted, ends with escaped'] = [
      '"Hello"""',
      ['Hello"'],
    ];
    $tests['quoted, escaped, missing comma'] = [
      '""""Hello""""',
      ['"'],
      ['Unexpected text after "@tag". Expected comma or end of text. Found "@unexpected".'],
    ];
    $tests['quoted, escaped, tag'] = [
      '"""",hello',
      ['"', 'hello'],
    ];
    $tests['quoted, double escaped, missing trailing quote'] = [
      '""""",hello',
      [],
      ['No ending quote character found.'],
    ];
    $tests['quoted, two escaped, comma, tag'] = [
      '"""""",hello',
      ['""', 'hello'],
    ];
    $tests['quoted, two escaped, word, two escaped'] = [
      '"""""Hello"""""',
      ['""Hello""'],
    ];
    $tests['unquoted, two words, escaped'] = [
      'hello ""world""',
      ['hello "world"'],
    ];
    $tests['word, escaped, unexpected quote'] = [
      'hello ""world"',
      [],
      ['Unexpected quote character found after "@tag"'],
    ];
    $tests['whitespace around unquoted, whitespace around quoted'] = [
      '    hello   ,    "world"    ',
      ['hello', 'world'],
    ];
    $tests['quoted, escaped quotes, escaped quotes on end'] = [
      '"Hello world ""Foo bar"""',
      ['Hello world "Foo bar"'],
    ];
    $tests['quoted, inner commas'] = [
      '"Hello, foo bar, World"',
      ['Hello, foo bar, World'],
    ];

    return $tests;
  }

}
