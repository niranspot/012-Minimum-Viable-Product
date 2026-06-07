<?php
class Validator {
    private $errors = [];
    private $data;

    public function __construct($data) {
        $this->data = $data;
    }

    public function required($field) {
        if (empty($this->data[$field])) {
            $this->errors[] = "$field is required";
        }
        return $this;
    }

    public function email($field) {
        if (!empty($this->data[$field]) && !filter_var($this->data[$field], FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = "$field must be a valid email";
        }
        return $this;
    }

    public function min($field, $length) {
        if (!empty($this->data[$field]) && strlen($this->data[$field]) < $length) {
            $this->errors[] = "$field must be at least $length characters";
        }
        return $this;
    }

    public function in($field, $allowed) {
        if (!empty($this->data[$field]) && !in_array($this->data[$field], $allowed)) {
            $this->errors[] = "$field must be one of: " . implode(', ', $allowed);
        }
        return $this;
    }

    public function fails() {
        return count($this->errors) > 0;
    }

    public function errors() {
        return $this->errors;
    }
}