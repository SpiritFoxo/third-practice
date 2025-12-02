<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;

class CmsController extends Controller {
  public function page(string $slug) {
    $row = DB::table('cms_pages')
            ->where('slug', $slug)
            ->first();

    if (!$row) abort(404);
    $safeContent = strip_tags($row->body, '<p><b><i><h3><h4><ul><li><br>');

    return response()->view('cms.page', [
        'title' => $row->title, 
        'html' => $safeContent
    ]);
  }
}