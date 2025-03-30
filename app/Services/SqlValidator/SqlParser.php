<?php

namespace App\Services\SqlValidator;

class SqlParser
{
    private $tokens;
    private $position = 0;
    private $errors = [];
    
    public function __construct(array $tokens)
    {
        $this->tokens = $tokens;
    }
    
    public function parse()
    {
        // Check for any lexer errors first
        foreach ($this->tokens as $token) {
            if ($token['type'] === 'error') {
                $this->errors[] = [
                    'message' => $token['message'] ?? 'Lexical error',
                    'line' => $token['line'],
                    'word' => $token['word']
                ];
            }
        }
        
        if (empty($this->errors)) {
            // If no lexical errors, proceed with syntax parsing
            $this->parseStatement();
        }
        
        return [
            'isValid' => empty($this->errors),
            'errors' => $this->errors
        ];
    }
    
    private function parseStatement()
    {
        if ($this->isAtEnd()) {
            $this->addError('Empty SQL statement');
            return;
        }
        
        $token = $this->peek();
        
        if ($this->match('keyword', 'SELECT')) {
            $this->parseSelectStatement();
        } else if ($this->match('keyword', 'INSERT')) {
            $this->parseInsertStatement();
        } else if ($this->match('keyword', 'UPDATE')) {
            $this->parseUpdateStatement();
        } else if ($this->match('keyword', 'DELETE')) {
            $this->parseDeleteStatement();
        } else if ($this->match('keyword', 'CREATE')) {
            $this->parseCreateStatement();
        } else if ($this->match('keyword', 'ALTER')) {
            $this->parseAlterStatement();
        } else if ($this->match('keyword', 'DROP')) {
            $this->parseDropStatement();
        } else {
            $this->addError("Unexpected keyword at start of statement: " . ($token['value'] ?? 'UNKNOWN'));
            $this->advance();
        }
        
        // Check for semicolon at the end
        if (!$this->isAtEnd() && !$this->match('special', ';')) {
            $this->addError("Expected ';' at the end of SQL statement");
        }
    }
    
    private function parseSelectStatement()
    {
        // Parse column list
        $this->parseColumnList();
        
        // Expect FROM
        if (!$this->consume('keyword', 'FROM', "Expected 'FROM' after SELECT column list")) {
            return;
        }
        
        // Parse table reference
        $this->parseTableReference();
        
        // Optional WHERE clause
        if ($this->match('keyword', 'WHERE')) {
            $this->parseCondition();
        }
        
        // Optional GROUP BY
        if ($this->match('keyword', 'GROUP')) {
            if (!$this->consume('keyword', 'BY', "Expected 'BY' after GROUP")) {
                return;
            }
            $this->parseColumnList();
            
            // Optional HAVING
            if ($this->match('keyword', 'HAVING')) {
                $this->parseCondition();
            }
        }
        
        // Optional ORDER BY
        if ($this->match('keyword', 'ORDER')) {
            if (!$this->consume('keyword', 'BY', "Expected 'BY' after ORDER")) {
                return;
            }
            $this->parseOrderByList();
        }
        
        // Optional LIMIT
        if ($this->match('keyword', 'LIMIT')) {
            if (!$this->parseNumber()) {
                return;
            }
            
            // Optional OFFSET or comma followed by another number
            if ($this->match('keyword', 'OFFSET')) {
                if (!$this->parseNumber()) {
                    return;
                }
            } else if ($this->match('special', ',')) {
                if (!$this->parseNumber()) {
                    return;
                }
            }
        }
    }
    
    private function parseInsertStatement()
    {
        // Expect INTO
        if (!$this->consume('keyword', 'INTO', "Expected 'INTO' after INSERT")) {
            return;
        }
        
        // Parse table name
        if (!$this->parseIdentifier()) {
            $this->addError("Expected table name after INSERT INTO");
            return;
        }
        
        // Check for optional column list
        if ($this->match('special', '(')) {
            $this->parseColumnList();
            if (!$this->consume('special', ')', "Expected ')' after column list")) {
                return;
            }
        }
        
        // Expect VALUES
        if (!$this->consume('keyword', 'VALUES', "Expected 'VALUES' keyword")) {
            return;
        }
        
        // Parse value list(s)
        if (!$this->parseValuesList()) {
            return;
        }
        
        // Parse additional value lists (if any)
        while ($this->match('special', ',')) {
            if (!$this->parseValuesList()) {
                return;
            }
        }
    }
    
    private function parseUpdateStatement()
    {
        // Parse table name
        if (!$this->parseIdentifier()) {
            $this->addError("Expected table name after UPDATE");
            return;
        }
        
        // Expect SET
        if (!$this->consume('keyword', 'SET', "Expected 'SET' after table name")) {
            return;
        }
        
        // Parse assignment list
        $this->parseAssignmentList();
        
        // Optional WHERE clause
        if ($this->match('keyword', 'WHERE')) {
            $this->parseCondition();
        }
    }
    
    private function parseDeleteStatement()
    {
        // Expect FROM
        if (!$this->consume('keyword', 'FROM', "Expected 'FROM' after DELETE")) {
            return;
        }
        
        // Parse table name
        if (!$this->parseIdentifier()) {
            $this->addError("Expected table name after DELETE FROM");
            return;
        }
        
        // Optional WHERE clause
        if ($this->match('keyword', 'WHERE')) {
            $this->parseCondition();
        }
    }
    
    private function parseCreateStatement()
    {
        // Check for CREATE TABLE
        if (!$this->consume('keyword', 'TABLE', "Expected 'TABLE' after CREATE")) {
            return;
        }
        
        // Parse table name
        if (!$this->parseIdentifier()) {
            $this->addError("Expected table name after CREATE TABLE");
            return;
        }
        
        // Expect opening parenthesis
        if (!$this->consume('special', '(', "Expected '(' after table name")) {
            return;
        }
        
        // Parse column definitions
        $this->parseColumnDefinitionList();
        
        // Expect closing parenthesis
        if (!$this->consume('special', ')', "Expected ')' after column definitions")) {
            return;
        }
    }
    
    private function parseAlterStatement()
    {
        // Check for ALTER TABLE
        if (!$this->consume('keyword', 'TABLE', "Expected 'TABLE' after ALTER")) {
            return;
        }
        
        // Parse table name
        if (!$this->parseIdentifier()) {
            $this->addError("Expected table name after ALTER TABLE");
            return;
        }
        
        // Check for ADD, DROP, or MODIFY
        if ($this->match('keyword', 'ADD')) {
            // Parse column definition or constraint
            if ($this->match('keyword', 'COLUMN')) {
                if (!$this->parseColumnDefinition()) {
                    return;
                }
            } else if ($this->match('keyword', 'CONSTRAINT') || 
                      $this->match('keyword', 'PRIMARY') || 
                      $this->match('keyword', 'FOREIGN') ||
                      $this->match('keyword', 'UNIQUE')) {
                // Just advance, we don't need to validate the specific constraint syntax
                while (!$this->isAtEnd() && !$this->check('special', ';')) {
                    $this->advance();
                }
            } else {
                // Assume it's a column definition
                if (!$this->parseColumnDefinition()) {
                    return;
                }
            }
        } else if ($this->match('keyword', 'DROP')) {
            if ($this->match('keyword', 'COLUMN')) {
                if (!$this->parseIdentifier()) {
                    $this->addError("Expected column name after DROP COLUMN");
                    return;
                }
            } else if ($this->match('keyword', 'CONSTRAINT') || 
                      $this->match('keyword', 'PRIMARY') || 
                      $this->match('keyword', 'FOREIGN') ||
                      $this->match('keyword', 'INDEX')) {
                // Just advance, we don't need to validate the specific syntax
                while (!$this->isAtEnd() && !$this->check('special', ';')) {
                    $this->advance();
                }
            } else {
                // Assume it's a column
                if (!$this->parseIdentifier()) {
                    $this->addError("Expected column name after DROP");
                    return;
                }
            }
        } else {
            $this->addError("Expected 'ADD' or 'DROP' after table name in ALTER TABLE statement");
            return;
        }
    }
    
    private function parseDropStatement()
    {
        // Check for DROP TABLE
        if (!$this->consume('keyword', 'TABLE', "Expected 'TABLE' after DROP")) {
            return;
        }
        
        // Parse table name
        if (!$this->parseIdentifier()) {
            $this->addError("Expected table name after DROP TABLE");
            return;
        }
    }
    
    private function parseColumnList()
    {
        // Handle SELECT * case
        if ($this->match('special', '*')) {
            return true;
        }
        
        if (!$this->parseExpression()) {
            $this->addError("Expected column expression");
            return false;
        }
        
        // Optional AS alias
        if ($this->match('keyword', 'AS')) {
            if (!$this->parseIdentifier()) {
                $this->addError("Expected alias name after AS");
                return false;
            }
        } else if ($this->check('identifier')) {
            // Implicit alias
            $this->advance();
        }
        
        // Handle additional columns
        while ($this->match('special', ',')) {
            if (!$this->parseExpression()) {
                $this->addError("Expected column expression after comma");
                return false;
            }
            
            // Optional AS alias
            if ($this->match('keyword', 'AS')) {
                if (!$this->parseIdentifier()) {
                    $this->addError("Expected alias name after AS");
                    return false;
                }
            } else if ($this->check('identifier')) {
                // Implicit alias
                $this->advance();
            }
        }
        
        return true;
    }
    
    private function parseTableReference()
    {
        if (!$this->parseIdentifier()) {
            $this->addError("Expected table name");
            return false;
        }
        
        // Optional AS alias
        if ($this->match('keyword', 'AS')) {
            if (!$this->parseIdentifier()) {
                $this->addError("Expected alias name after AS");
                return false;
            }
        } else if ($this->check('identifier')) {
            // Implicit alias
            $this->advance();
        }
        
        // Check for JOINs
        while (
            $this->match('keyword', 'JOIN') || 
            $this->checkAndConsumeCompoundKeyword(['LEFT', 'JOIN']) ||
            $this->checkAndConsumeCompoundKeyword(['RIGHT', 'JOIN']) ||
            $this->checkAndConsumeCompoundKeyword(['INNER', 'JOIN']) ||
            $this->checkAndConsumeCompoundKeyword(['LEFT', 'OUTER', 'JOIN']) ||
            $this->checkAndConsumeCompoundKeyword(['RIGHT', 'OUTER', 'JOIN'])
        ) {
            if (!$this->parseIdentifier()) {
                $this->addError("Expected table name after JOIN");
                return false;
            }
            
            // Optional AS alias
            if ($this->match('keyword', 'AS')) {
                if (!$this->parseIdentifier()) {
                    $this->addError("Expected alias name after AS");
                    return false;
                }
            } else if ($this->check('identifier')) {
                // Implicit alias
                $this->advance();
            }
            
            // ON condition
            if (!$this->consume('keyword', 'ON', "Expected 'ON' after JOIN table")) {
                return false;
            }
            
            if (!$this->parseCondition()) {
                return false;
            }
        }
        
        return true;
    }
    
    private function parseCondition()
    {
        if (!$this->parseExpression()) {
            $this->addError("Expected expression in condition");
            return false;
        }
        
        // Check for boolean operators
        while ($this->match('keyword', 'AND') || $this->match('keyword', 'OR')) {
            if (!$this->parseExpression()) {
                $this->addError("Expected expression after AND/OR");
                return false;
            }
        }
        
        return true;
    }
    
    private function parseExpression()
    {
        // Simple expression parsing - in a real parser, this would be more complex
        if (
            $this->check('identifier') || 
            $this->check('number') || 
            $this->check('string') ||
            $this->check('special', '(') ||
            $this->check('special', '*')
        ) {
            if ($this->check('special', '(')) {
                $this->advance();
                $this->parseExpression();
                if (!$this->consume('special', ')', "Expected ')' after expression")) {
                    return false;
                }
            } else {
                $this->advance();
            }
            
            // Check for operators
            if ($this->check('operator') || 
                $this->check('keyword', 'IS') || 
                $this->check('keyword', 'LIKE') || 
                $this->check('keyword', 'IN') || 
                $this->check('keyword', 'BETWEEN')) {
                
                $this->advance();
                
                // Special handling for IS NULL / IS NOT NULL
                if ($this->previous()['value'] === 'IS') {
                    if ($this->match('keyword', 'NOT')) {
                        if (!$this->consume('keyword', 'NULL', "Expected NULL after IS NOT")) {
                            return false;
                        }
                    } else if (!$this->consume('keyword', 'NULL', "Expected NULL after IS")) {
                        return false;
                    }
                } 
                // Special handling for IN (...)
                else if ($this->previous()['value'] === 'IN') {
                    if (!$this->consume('special', '(', "Expected '(' after IN")) {
                        return false;
                    }
                    
                    // Parse list of values
                    if (!$this->parseValuesList()) {
                        return false;
                    }
                    
                    if (!$this->consume('special', ')', "Expected ')' after IN list")) {
                        return false;
                    }
                }
                // Special handling for BETWEEN ... AND ...
                else if ($this->previous()['value'] === 'BETWEEN') {
                    if (!$this->parseExpression()) {
                        $this->addError("Expected lower bound expression after BETWEEN");
                        return false;
                    }
                    
                    if (!$this->consume('keyword', 'AND', "Expected AND after BETWEEN lower bound")) {
                        return false;
                    }
                    
                    if (!$this->parseExpression()) {
                        $this->addError("Expected upper bound expression after AND");
                        return false;
                    }
                }
                // Normal operator
                else {
                    if (!$this->parseExpression()) {
                        $this->addError("Expected right hand expression after operator");
                        return false;
                    }
                }
            }
            
            return true;
        }
        
        return false;
    }
    
    private function parseOrderByList()
    {
        if (!$this->parseIdentifier()) {
            $this->addError("Expected column name in ORDER BY");
            return false;
        }
        
        // Optional ASC/DESC
        $this->match('keyword', 'ASC') || $this->match('keyword', 'DESC');
        
        // Handle additional columns
        while ($this->match('special', ',')) {
            if (!$this->parseIdentifier()) {
                $this->addError("Expected column name after comma in ORDER BY");
                return false;
            }
            
            // Optional ASC/DESC
            $this->match('keyword', 'ASC') || $this->match('keyword', 'DESC');
        }
        
        return true;
    }
    
    private function parseValuesList()
    {
        if (!$this->consume('special', '(', "Expected '(' for values list")) {
            return false;
        }
        
        if (!$this->parseValue()) {
            $this->addError("Expected value in values list");
            return false;
        }
        
        while ($this->match('special', ',')) {
            if (!$this->parseValue()) {
                $this->addError("Expected value after comma in values list");
                return false;
            }
        }
        
        if (!$this->consume('special', ')', "Expected ')' after values list")) {
            return false;
        }
        
        return true;
    }
    
    private function parseValue()
    {
        if (
            $this->check('string') ||
            $this->check('number') ||
            $this->check('keyword', 'NULL') ||
            $this->check('keyword', 'DEFAULT')
        ) {
            $this->advance();
            return true;
        }
        
        return false;
    }
    
    private function parseAssignmentList()
    {
        if (!$this->parseAssignment()) {
            return false;
        }
        
        while ($this->match('special', ',')) {
            if (!$this->parseAssignment()) {
                $this->addError("Expected assignment after comma");
                return false;
            }
        }
        
        return true;
    }
    
    private function parseAssignment()
    {
        if (!$this->parseIdentifier()) {
            $this->addError("Expected column name in assignment");
            return false;
        }
        
        if (!$this->consume('operator', '=', "Expected '=' in assignment")) {
            return false;
        }
        
        if (!$this->parseExpression()) {
            $this->addError("Expected expression in assignment");
            return false;
        }
        
        return true;
    }
    
    private function parseColumnDefinitionList()
    {
        if (!$this->parseColumnDefinition()) {
            return false;
        }
        
        while ($this->match('special', ',')) {
            if (
                $this->check('keyword', 'PRIMARY') ||
                $this->check('keyword', 'FOREIGN') ||
                $this->check('keyword', 'UNIQUE') ||
                $this->check('keyword', 'CONSTRAINT')
            ) {
                // Handle table constraints
                // Just advance, we don't need to validate the specific constraint syntax
                $this->advance();
                while (
                    !$this->isAtEnd() && 
                    !$this->check('special', ',') && 
                    !$this->check('special', ')')
                ) {
                    $this->advance();
                }
            } else {
                if (!$this->parseColumnDefinition()) {
                    return false;
                }
            }
        }
        
        return true;
    }
    
    private function parseColumnDefinition()
    {
        if (!$this->parseIdentifier()) {
            $this->addError("Expected column name in column definition");
            return false;
        }
        
        // Parse data type
        if (!$this->parseDataType()) {
            return false;
        }
        
        // Parse column constraints
        while (
            !$this->isAtEnd() && 
            !$this->check('special', ',') && 
            !$this->check('special', ')')
        ) {
            if (
                $this->match('keyword', 'NOT') &&
                !$this->consume('keyword', 'NULL', "Expected NULL after NOT in column constraint")
            ) {
                return false;
            } else if (
                $this->match('keyword', 'DEFAULT') && 
                !$this->parseValue()
            ) {
                $this->addError("Expected value after DEFAULT in column constraint");
                return false;
            } else if (
                $this->match('keyword', 'PRIMARY') &&
                $this->match('keyword', 'KEY')
            ) {
                // PRIMARY KEY - no additional parsing needed
            } else if (
                $this->match('keyword', 'UNIQUE')
            ) {
                // UNIQUE - no additional parsing needed
            } else if (
                $this->match('keyword', 'REFERENCES')
            ) {
                // FOREIGN KEY reference
                if (!$this->parseIdentifier()) {
                    $this->addError("Expected table name after REFERENCES");
                    return false;
                }
                
                if ($this->match('special', '(')) {
                    if (!$this->parseIdentifier()) {
                        $this->addError("Expected column name in references");
                        return false;
                    }
                    
                    if (!$this->consume('special', ')', "Expected ')' after referenced column")) {
                        return false;
                    }
                }
                
                // ON DELETE / ON UPDATE actions
                while (
                    $this->match('keyword', 'ON')
                ) {
                    if (
                        !($this->match('keyword', 'DELETE') || $this->match('keyword', 'UPDATE'))
                    ) {
                        $this->addError("Expected DELETE or UPDATE after ON in REFERENCES");
                        return false;
                    }
                    
                    if (
                        !($this->match('keyword', 'CASCADE') || 
                          $this->match('keyword', 'RESTRICT') || 
                          $this->match('keyword', 'SET') || 
                          $this->match('keyword', 'NO'))
                    ) {
                        $this->addError("Expected action after ON DELETE/UPDATE");
                        return false;
                    }
                    
                    if ($this->previous()['value'] === 'SET') {
                        if (!$this->consume('keyword', 'NULL', "Expected NULL after SET")) {
                            return false;
                        }
                    } else if ($this->previous()['value'] === 'NO') {
                        if (!$this->consume('keyword', 'ACTION', "Expected ACTION after NO")) {
                            return false;
                        }
                    }
                }
            } else {
                // Unknown constraint
                $this->advance();
            }
        }
        
        return true;
    }
    
    private function parseDataType()
    {
        if (!$this->check('identifier')) {
            $this->addError("Expected data type");
            return false;
        }
        
        $this->advance();
        
        // Check for type with length/precision specification - e.g., VARCHAR(255)
        if ($this->match('special', '(')) {
            // Parse length or precision/scale
            if (!$this->parseNumber()) {
                $this->addError("Expected length/precision in data type specification");
                return false;
            }
            
            // Check for scale (for DECIMAL/NUMERIC types)
            if ($this->match('special', ',')) {
                if (!$this->parseNumber()) {
                    $this->addError("Expected scale after comma in data type specification");
                    return false;
                }
            }
            
            if (!$this->consume('special', ')', "Expected ')' after type length/precision")) {
                return false;
            }
        }
        
        return true;
    }
    
    private function parseNumber()
    {
        if (!$this->check('number')) {
            $this->addError("Expected number");
            return false;
        }
        
        $this->advance();
        return true;
    }
    
    private function parseIdentifier()
    {
        if (!$this->check('identifier') && !$this->checkKeywordAsIdentifier()) {
            return false;
        }
        
        $this->advance();
        
        // Check for dot notation (table.column)
        if ($this->match('special', '.')) {
            if (!$this->check('identifier') && !$this->check('special', '*')) {
                $this->addError("Expected identifier or * after .");
                return false;
            }
            
            $this->advance();
        }
        
        return true;
    }
    
    private function checkKeywordAsIdentifier()
    {
        // Some keywords can be used as identifiers in certain contexts
        return $this->check('keyword');
    }
    
    private function checkAndConsumeCompoundKeyword(array $keywords)
    {
        $savedPosition = $this->position;
        
        foreach ($keywords as $keyword) {
            if (!$this->match('keyword', $keyword)) {
                $this->position = $savedPosition;
                return false;
            }
        }
        
        return true;
    }
    
    private function isAtEnd()
    {
        return $this->position >= count($this->tokens);
    }
    
    private function peek()
    {
        if ($this->isAtEnd()) {
            return null;
        }
        return $this->tokens[$this->position];
    }
    
    private function previous()
    {
        return $this->tokens[$this->position - 1];
    }
    
    private function advance()
    {
        if (!$this->isAtEnd()) {
            $this->position++;
        }
        return $this->previous();
    }
    
    private function check($type, $value = null)
    {
        if ($this->isAtEnd()) {
            return false;
        }
        
        $token = $this->peek();
        
        if ($token['type'] !== $type) {
            return false;
        }
        
        if ($value !== null && $token['value'] !== $value) {
            return false;
        }
        
        return true;
    }
    
    private function match($type, $value = null)
    {
        if ($this->check($type, $value)) {
            $this->advance();
            return true;
        }
        
        return false;
    }
    
    private function consume($type, $value, $errorMessage)
    {
        if ($this->match($type, $value)) {
            return true;
        }
        
        $this->addError($errorMessage);
        return false;
    }
    
    private function addError($message)
    {
        $token = $this->peek();
        $line = $token ? $token['line'] : 0;
        $word = $token ? $token['word'] : 0;
        
        $this->errors[] = [
            'message' => $message,
            'line' => $line,
            'word' => $word
        ];
    }
}