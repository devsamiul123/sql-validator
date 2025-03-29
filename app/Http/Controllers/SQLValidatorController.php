<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use PHPSQLParser\PHPSQLParser;
use Illuminate\Support\Facades\Validator;

class SQLValidatorController extends Controller
{
    /**
     * Validate SQL query
     */
    public function validate(Request $request)
    {
        $validated = $request->validate([
            'query' => 'required|string',
        ]);

        $query = $validated['query'];
        
        try {
            // Initialize the parser
            $parser = new PHPSQLParser();
            
            // Parse the SQL query
            $parsed = $parser->parse($query);
            
            // If we got here without exceptions, the SQL is valid
            return response()->json([
                'valid' => true,
                'parsed' => $parsed
            ]);
        } catch (\Exception $e) {
            // Extract line and position information from the error message
            $errorInfo = $this->extractErrorInfo($e->getMessage(), $query);
            
            return response()->json([
                'valid' => false,
                'error' => $e->getMessage(),
                'line' => $errorInfo['line'],
                'position' => $errorInfo['position'],
                'errorWord' => $errorInfo['word']
            ]);
        }
    }
    
    /**
     * Extract line and position information from error message
     */
    private function extractErrorInfo(string $errorMessage, string $query): array
    {
        // Default values
        $result = [
            'line' => 1,
            'position' => 0,
            'word' => ''
        ];
        
        // Parse the error message to find the position of the error
        if (preg_match('/near \'(.*?)\' at line (\d+)/', $errorMessage, $matches)) {
            $errorWord = $matches[1];
            $lineNumber = (int)$matches[2];
            
            $result['line'] = $lineNumber;
            
            // Split the query into lines
            $lines = explode("\n", $query);
            
            // Make sure the line number is valid
            if ($lineNumber <= count($lines)) {
                $line = $lines[$lineNumber - 1];
                $position = strpos($line, $errorWord);
                
                if ($position !== false) {
                    // Count words until the error position
                    $wordsBeforeError = substr($line, 0, $position);
                    $wordCount = count(array_filter(explode(' ', $wordsBeforeError)));
                    
                    $result['position'] = $position;
                    $result['word'] = $wordCount + 1; // +1 because the error is at the next word
                }
            }
        }
        
        return $result;
    }
}