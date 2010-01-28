<?php
/**
 * Interface implemented by formatter implementations for the rest client
 */
interface RestClientFormatter {
  /**
   * Serializes arbitrary data to the implemented format.
   *
   * @param mixed $data
   *  The data that should be serialized.
   * @return string
   *  The serialized data as a string.
   */
  function serialize($data);

  /**
   * Unserializes data in the implemented format.
   *
   * @param string $data
   *  The data that should be unserialized.
   * @return mixed
   *  The unserialized data.
   */
  function unserialize($data);
}

/**
 * Interface that should be implemented by classes that provides a
 * authentication method for the rest client.
 */
interface RestClientAuthentication {
  /**
   * Used by the RestClient to authenticate requests.
   *
   * @param RestClientRequest $request
   * @return void
   */
  function authenticate($request);
}

/**
 * Interface describing the methods needed for sending data.
 */
interface RestClientSender {

}