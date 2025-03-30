<?php

namespace App\Services\SqlValidator;

class SqlValidator
{
    public function validate(string $sql)
    {
        // First, tokenize the input using the lexer
        $lexer = new SqlLexer($sql);
        $tokens = $lexer->tokenize();
        
        // Then, parse the tokens
        $parser = new SqlParser($tokens);
        $result = $parser->parse();
        
        // Prepare the response
        $response = [
            'isValid' => $result['isValid'],
            'errors' => []
        ];
        
        // Format error messages
        foreach ($result['errors'] as $error) {
            $response['errors'][] = [
                'message' => $error['message'],
                'line' => $error['line'],
                'word' => $error['word'],
                'lineContent' => $this->getLineContent($sql, $error['line'])
            ];
        }
        
        return $response;
    }
    
    private function getLineContent(string $sql, int $lineNumber)
    {
        $lines = explode("\n", $sql);
        if (isset($lines[$lineNumber - 1])) {
            return $lines[$lineNumber - 1];
        }
        
        return '';
    }
}