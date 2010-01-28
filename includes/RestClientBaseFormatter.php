<?php
/**
 * Implements basic response formatters for
 * serialized PHP and JSON.
 */
class RestClientBaseFormatter implements RestClientFormatter {
  const FORMAT_PHP  = 'php';
  const FORMAT_JSON = 'json';

  private $format;

  /**
   * Constructs the formatter
   *
   * @param string format
   *  Expected format, can be either 'php' or 'json'
   * @throws InvalidArgumentException
   */
  public function __construct($format=self::FORMAT_PHP) {
    // Some people might think it should be PHP or JSON
    $format = strtolower($format);

    if (!in_array($format, array('php', 'json'))) {
      throw new InvalidArgumentException('RestClientBaseFormatter can only handle serialized PHP or JSON.');
    }

    $this->format = $format;
  }

  /**
   * Serializes arbitrary data.
   *
   * @param mixed $data
   *  The data that should be serialized.
   * @return string
   *  The serialized data as a string.
   */
  public function serialize($data) {
    switch($this->format) {
      case self::FORMAT_PHP:
        return serialize($data);
        break;
      case self::FORMAT_JSON:
        return json_encode($data);
        break;
    }
  }

  /**
   * Unserializes data.
   *
   * @param string $data
   *  The data that should be unserialized.
   * @return mixed
   *  The unserialized data.
   * @throws Exception
   */
  public function unserialize($data) {
    switch($this->format) {
      case self::FORMAT_PHP:
        if (($res = @unserialize($data)) !== FALSE || $data === serialize(FALSE)) {
          return $res;
        }
        else {
          throw new Exception(t('Unserialization of response body failed.'), 1);
        }
        break;
      case self::FORMAT_JSON:
        return json_decode($data);
        break;
    }
  }
}