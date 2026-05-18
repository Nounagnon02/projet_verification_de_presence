<?php\n\nuse Illuminate\Support\Facades\Route;\n\nRoute::get('/', function () {\n    return response()->json(['message' => 'API SGP Backend Rentrée 2026']);\n});
