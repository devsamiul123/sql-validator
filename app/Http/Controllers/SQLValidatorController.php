<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\SqlValidator\SqlValidator;

class SQLValidatorController extends Controller
{
    protected $sqlValidator;

    public function __construct(SqlValidator $sqlValidator)
    {
        $this->sqlValidator = $sqlValidator;
    }

    public function validate(Request $request)
    {
        $request->validate([
            'sql' => 'required|string'
        ]);

        $sql = $request->input('sql');
        $result = $this->sqlValidator->validate($sql);

        return response()->json($result);
    }
}