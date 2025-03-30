<?php

namespace App\Services\SqlValidator;

class SqlLexer
{
    private $input;
    private $position = 0;
    private $tokens = [];
    private $lineNumber = 1;
    private $wordNumber = 1;
    private $linePositions = [1]; // Start with line 1
    
    // Keywords for MySQL CRUD operations
    private $keywords = [
        'SELECT', 'FROM', 'WHERE', 'INSERT', 'INTO', 'VALUES',
        'UPDATE', 'SET', 'DELETE', 'JOIN', 'LEFT', 'RIGHT', 'INNER',
        'OUTER', 'GROUP', 'BY', 'HAVING', 'ORDER', 'ASC', 'DESC',
        'LIMIT', 'OFFSET', 'AS', 'ON', 'AND', 'OR', 'NOT', 'NULL',
        'IS', 'IN', 'BETWEEN', 'LIKE', 'CREATE', 'TABLE', 'ALTER',
        'DROP', 'COLUMN', 'ADD', 'PRIMARY', 'KEY', 'FOREIGN', 'REFERENCES',
        'CASCADE', 'RESTRICT', 'DEFAULT', 'UNIQUE', 'INDEX', 'CONSTRAINT'
    ];
    
    private $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=', '+', '-', '*', '/', '%'
    ];
    
    private $specialCharacters = [
        '(', ')', ',', ';', '.', '*'
    ];
    
    public function __construct(string $input)
    {
        $this->input = $input;
    }
    
    public function tokenize()
    {
        while ($this->position < strlen($this->input)) {
            $char = $this->input[$this->position];
            
            // Skip whitespace but count lines and words
            if ($this->isWhitespace($char)) {
                if ($char === "\n") {
                    $this->lineNumber++;
                    $this->wordNumber = 1;
                    $this->linePositions[$this->lineNumber] = $this->position + 1;
                }
                $this->position++;
                continue;
            }
            
            // Handle identifiers (table names, column names)
            if ($this->isAlpha($char) || $char === '_') {
                $this->tokenizeIdentifier();
                continue;
            }
            
            // Handle numbers
            if ($this->isDigit($char)) {
                $this->tokenizeNumber();
                continue;
            }
            
            // Handle strings
            if ($char === "'" || $char === '"') {
                $this->tokenizeString($char);
                continue;
            }
            
            // Handle comments
            if ($char === '-' && $this->peekNext() === '-') {
                $this->skipLineComment();
                continue;
            }
            
            if ($char === '/' && $this->peekNext() === '*') {
                $this->skipBlockComment();
                continue;
            }
            
            // Handle operators
            if ($this->isOperatorStart($char)) {
                $this->tokenizeOperator();
                continue;
            }
            
            // Handle special characters
            if (in_array($char, $this->specialCharacters)) {
                $this->tokens[] = [
                    'type' => 'special',
                    'value' => $char,
                    'line' => $this->lineNumber,
                    'word' => $this->wordNumber,
                    'position' => $this->position
                ];
                $this->position++;
                continue;
            }
            
            // If we get here, we have an unexpected character
            $this->tokens[] = [
                'type' => 'error',
                'value' => $char,
                'line' => $this->lineNumber,
                'word' => $this->wordNumber,
                'position' => $this->position,
                'message' => "Unexpected character: '$char'"
            ];
            $this->position++;
        }
        
        return $this->tokens;
    }
    
    private function tokenizeIdentifier()
    {
        $start = $this->position;
        $line = $this->lineNumber;
        $word = $this->wordNumber;
        
        while ($this->position < strlen($this->input) && 
               ($this->isAlphaNumeric($this->input[$this->position]) || $this->input[$this->position] === '_')) {
            $this->position++;
        }
        
        $value = substr($this->input, $start, $this->position - $start);
        $type = in_array(strtoupper($value), $this->keywords) ? 'keyword' : 'identifier';
        
        $this->tokens[] = [
            'type' => $type,
            'value' => $value,
            'line' => $line,
            'word' => $word,
            'position' => $start
        ];
        
        $this->wordNumber++;
    }
    
    private function tokenizeNumber()
    {
        $start = $this->position;
        $line = $this->lineNumber;
        $word = $this->wordNumber;
        $isFloat = false;
        
        while ($this->position < strlen($this->input)) {
            if ($this->isDigit($this->input[$this->position])) {
                $this->position++;
            } else if ($this->input[$this->position] === '.' && !$isFloat) {
                $isFloat = true;
                $this->position++;
            } else {
                break;
            }
        }
        
        $value = substr($this->input, $start, $this->position - $start);
        
        $this->tokens[] = [
            'type' => 'number',
            'value' => $value,
            'line' => $line,
            'word' => $word,
            'position' => $start
        ];
        
        $this->wordNumber++;
    }
    
    private function tokenizeString($quote)
    {
        $start = $this->position;
        $line = $this->lineNumber;
        $word = $this->wordNumber;
        $this->position++; // Skip opening quote
        
        $value = '';
        $closed = false;
        
        while ($this->position < strlen($this->input)) {
            $char = $this->input[$this->position];
            
            if ($char === "\n") {
                break; // Error: Unterminated string
            }
            
            if ($char === $quote) {
                if ($this->position + 1 < strlen($this->input) && $this->input[$this->position + 1] === $quote) {
                    // Escaped quote
                    $value .= $quote;
                    $this->position += 2;
                } else {
                    // End of string
                    $this->position++;
                    $closed = true;
                    break;
                }
            } else {
                $value .= $char;
                $this->position++;
            }
        }
        
        $type = $closed ? 'string' : 'error';
        $message = $closed ? null : "Unterminated string";
        
        $this->tokens[] = [
            'type' => $type,
            'value' => $value,
            'line' => $line,
            'word' => $word,
            'position' => $start,
            'message' => $message
        ];
        
        $this->wordNumber++;
    }
    
    private function tokenizeOperator()
    {
        $start = $this->position;
        $line = $this->lineNumber;
        $word = $this->wordNumber;
        
        // Check for two-character operators
        if ($this->position + 1 < strlen($this->input)) {
            $twoChars = $this->input[$this->position] . $this->input[$this->position + 1];
            if (in_array($twoChars, $this->operators)) {
                $this->tokens[] = [
                    'type' => 'operator',
                    'value' => $twoChars,
                    'line' => $line,
                    'word' => $word,
                    'position' => $start
                ];
                $this->position += 2;
                $this->wordNumber++;
                return;
            }
        }
        
        // Single character operator
        $this->tokens[] = [
            'type' => 'operator',
            'value' => $this->input[$this->position],
            'line' => $line,
            'word' => $word,
            'position' => $start
        ];
        $this->position++;
        $this->wordNumber++;
    }
    
    private function skipLineComment()
    {
        $this->position += 2; // Skip '--'
        
        while ($this->position < strlen($this->input) && $this->input[$this->position] !== "\n") {
            $this->position++;
        }
    }
    
    private function skipBlockComment()
    {
        $this->position += 2; // Skip '/*'
        
        while ($this->position + 1 < strlen($this->input)) {
            if ($this->input[$this->position] === '*' && $this->input[$this->position + 1] === '/') {
                $this->position += 2; // Skip '*/'
                return;
            }
            
            if ($this->input[$this->position] === "\n") {
                $this->lineNumber++;
                $this->wordNumber = 1;
                $this->linePositions[$this->lineNumber] = $this->position + 1;
            }
            
            $this->position++;
        }
        
        // Error: Unterminated block comment
        $this->tokens[] = [
            'type' => 'error',
            'value' => '/*',
            'line' => $this->lineNumber,
            'word' => $this->wordNumber,
            'position' => $this->position - 2,
            'message' => 'Unterminated block comment'
        ];
    }
    
    private function isWhitespace($char)
    {
        return $char === ' ' || $char === "\t" || $char === "\n" || $char === "\r";
    }
    
    private function isAlpha($char)
    {
        return ($char >= 'a' && $char <= 'z') || ($char >= 'A' && $char <= 'Z');
    }
    
    private function isDigit($char)
    {
        return $char >= '0' && $char <= '9';
    }
    
    private function isAlphaNumeric($char)
    {
        return $this->isAlpha($char) || $this->isDigit($char);
    }
    
    private function isOperatorStart($char)
    {
        foreach ($this->operators as $op) {
            if ($op[0] === $char) {
                return true;
            }
        }
        return false;
    }
    
    private function peekNext()
    {
        if ($this->position + 1 < strlen($this->input)) {
            return $this->input[$this->position + 1];
        }
        return null;
    }
    
    public function getLinePosition($lineNumber)
    {
        return $this->linePositions[$lineNumber] ?? 0;
    }
}