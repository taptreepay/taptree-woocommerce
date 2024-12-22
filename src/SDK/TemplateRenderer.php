<?php

namespace TapTree\WooCommerce\SDK;

class TemplateRenderer
{
  /**
   * Renders a template with the provided data.
   *
   * @param string $template
   * @param array $data
   * @return string
   */
  public static function render(string $template, array $data): string
  {
    // Handle conditional sections {{#key}}...{{/key}}
    $template = preg_replace_callback(
      '/{{\s*#(\w+)\s*}}(.*?){{\s*\/\1\s*}}/s',
      function ($matches) use ($data) {
        $key = $matches[1];
        $content = $matches[2];
        return !empty($data[$key]) ? self::render($content, $data) : '';
      },
      $template
    );

    // Handle inverted sections {{^key}}...{{/key}}
    $template = preg_replace_callback(
      '/{{\s*\^(\w+)\s*}}(.*?){{\s*\/\1\s*}}/s',
      function ($matches) use ($data) {
        $key = $matches[1];
        $content = $matches[2];
        return empty($data[$key]) ? self::render($content, $data) : '';
      },
      $template
    );

    // Replace variables {{key}} with their values
    return preg_replace_callback(
      '/{{\s*([\w\.]+)\s*}}/',
      function ($matches) use ($data) {
        $keys = explode('.', $matches[1]);
        $value = $data;
        foreach ($keys as $key) {
          if (is_array($value) && array_key_exists($key, $value)) {
            $value = $value[$key];
          } else {
            return $matches[0]; // Leave placeholder unchanged if key is missing
          }
        }
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
      },
      $template
    );
  }
}
