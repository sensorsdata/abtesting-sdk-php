<?php
require_once("cache.php");


/**
 * Class that implements the concept of an LRU Cache
 * using an associative array as a naive hashmap, and a doubly linked list
 * to control the access and insertion order.
 *
 * @author Rogério Vicente
 * @license MIT (see the LICENSE file for details)
 */
class LRUCache {
    // cache server to store nodes
    public $cache_server;
    
    // object Node representing the head of the list
    private $head;

    // object Node representing the tail of the list
    private $tail;

    // int the max number of elements the cache supports
    private $capacity;

    // Array representing a naive hashmap (TODO needs to pass the key through a hash function)
    private $hashmap;

    private $prefix;

    // cache time
    static public $cache_time;

    /**
     * @param int $capacity the max number of elements the cache allows
     */
    public function __construct($type, $host, $port, $auth = null, $prefix = "", $capacity = 4096, $cache_time = 86400) {
        $this->cache_server = new Cache($type, $host, $port, $auth);
        $this->capacity = $capacity;
        

        $this->prefix = $prefix;

        $this::$cache_time = $cache_time;

        $this->head = new Node($this->cache_server, $prefix.'_head', null);
        $this->tail = new Node($this->cache_server, $prefix.'_tail', null);

        if (!$this->head->hasNext()) {
            $this->head->setNext($this->tail);
        }

        if (!$this->tail->hasPrevious()) {
            $this->tail->setPrevious($this->head);
        }
    }

    /**
     * Get an element with the given key
     * @param string $key the key of the element to be retrieved
     * @return mixed the content of the element to be retrieved
     */
    public function get($key) {
        $key = $this->prefix.'.node.'.$key;

        $node = new Node($this->cache_server, $key);

        $data = $node -> getData();
        if (empty($data)) {
            return null;
        }
        // refresh the access
        $this->detach($node);
        $this->attach($this->head, $node);

        return $data;
    }

    /**
     * Inserts a new element into the cache 
     * @param string $key the key of the new element
     * @param string $data the content of the new element
     * @return boolean true on success, false if cache has zero capacity
     */
    public function put($key, $data) {
        $key = $this->prefix.'.node.'.$key;
        if ($this->capacity <= 0) { return false; }

        $node = new Node($this->cache_server, $key);

        // has data in memory, update data
        if ($node->getData()) {
            // update data
            $this->detach($node);
            $this->attach($this->head, $node);
            $node->setData($data);
        }
        // no data in memory, create node
        else {
            $node->setData($data);
            $this->attach($this->head, $node);

            $this->clean_old_nodes();
        }
        return true;
    }

    private function clean_old_nodes() {
        // check if cache is full
        $cache_size = $this->cache_size();
        if ($cache_size > $this->capacity) {
            // we're full, remove the tail
            $nodeToRemove = $this->tail->getPrevious();
            $this->detach($nodeToRemove);
            $nodeToRemove->remove();

            $this->clean_old_nodes();
        }
    }

    public function cache_size() {
        return $this->cache_server->nodes_size($this->prefix.'.node.*');
    }

    /**
     * Adds a node to the head of the list
     * @param Node $head the node object that represents the head of the list
     * @param Node $node the node to move to the head of the list
     */
    private function attach($head, $node) {
        $node->setPrevious($head, false);
        $node->setNext($head->getNext(), false);
        $node->getNext()->setPrevious($node);
        $node->getPrevious()->setNext($node);
        $node->flush();
    }

    /**
     * Removes a node from the list
     * @param Node $node the node to remove from the list
     */
    private function detach($node) {
        $node->getPrevious()->setNext($node->getNext());
        $node->getNext()->setPrevious($node->getPrevious());
    }
}

/**
 * Class that represents a node in a doubly linked list
 */
class Node {

    /**
     * the key of the node, this might seem reduntant,
     * but without this duplication, we don't have a fast way
     * to retrieve the key of a node when we wan't to remove it
     * from the hashmap.
     */
    private $key;

    // the content of the node
    private $data;

    // the next node
    private $next;

    // the previous node
    private $previous;

    // the cache server, memcached or redis
    private $cache_server;

    static private $hashmap;

    /**
     * @param string $key the key of the node
     * @param string $data the content of the node
     * @param object $cache_server remote cache server
     */
    public function __construct($cache_server, $key, $data = null) {
        if (empty($key)) {
            return $this;
        }
        $this->key = $key;
        $this->cache_server = $cache_server;
        if (empty($this::$hashmap)) {
            $this::$hashmap = array();
        }

        $remote_data = $this->cache_server->get($key);
        if (empty($remote_data)) {
            return $this;
        }

        $this->previous = $remote_data->previous;
        $this->next = $remote_data->next;

        if (empty($data)) {
            $this->setData($remote_data->data, false);
        } else {
            $this->setData($data, false);
        }

        $this::$hashmap[$key] = $this;
    }

    /**
     * Sets a new value for the node data
     * @param string the new content of the node
     */
    public function setData($data, $flush = true) {
        $this->data = $data;
        if ($flush) {
            $this->flush();
        }
    }

    public function hasNext() {
        return $this->next ? true : false;
    }

    public function hasPrevious() {
        return $this->previous ? true : false;
    }

    /**
     * Sets a node as the next node
     * @param Node $next the next node
     */
    public function setNext($next, $flush = true) {
        $this->next = $next;
        if ($flush) {
            $this->flush();
        }
    }

    /**
     * Sets a node as the previous node
     * @param Node $previous the previous node
     */
    public function setPrevious($previous, $flush = true) {
        $this->previous = $previous;
        if ($flush) {
            $this->flush();
        }
    }

    /**
     * flush data into cache server
     */
    public function flush() {
        $encoded_node = json_encode(array(
            'previous' => $this->previous instanceof Node ? $this->previous->getKey(): $this->previous,
            'next' => $this->next instanceof Node ? $this->next->getKey(): $this->next,
            'data' => $this->data
        ));

        $this::$hashmap[$this->key] = $this;

        $cache_time = LRUCache::$cache_time;
        $expire = (new DateTime())->getTimestamp() + $cache_time;

        $this->cache_server->set($this->key, $encoded_node, $expire);
    }

    public function remove() {
        unset($this::$hashmap[$this->key]);
        $this->cache_server->remove($this->key);
    }


    /**
     * Returns the node key
     * @return string the key of the node
     */
    public function getKey() {
        return $this->key;
    }

    /**
     * Returns the node data
     * @return mixed the content of the node
     */
    public function getData() {
        return $this->data;
    }

    /**
     * Returns the next node
     * @return Node the next node of the node
     */
    public function getNext() {
        if ($this->next instanceof Node) {
            return $this->next;
        }

        if (isset($this::$hashmap[$this->next])) {
            return $this::$hashmap[$this->next];
        }
        return new Node($this->cache_server, $this->next);
    }

    /**
     * Returns the previous node
     * @return Node the previous node of the node
     */
    public function getPrevious() {
        if ($this->previous instanceof Node) {
            return $this->previous;
        }

        if (isset($this::$hashmap[$this->previous])) {
            return $this::$hashmap[$this->previous];
        }

        return new Node($this->cache_server, $this->previous);
    }
}