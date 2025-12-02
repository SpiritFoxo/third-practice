<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UploadController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $file = $request->file('file');
        $name = Str::uuid() . '.' . $file->getClientOriginalExtension();

        $file->move(public_path('uploads'), $name);

        return back()->with('status', 'Файл безопасно загружен: ' . $name);
    }
}