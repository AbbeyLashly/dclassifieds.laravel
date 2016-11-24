<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Page;

use Cache;

class PageController extends Controller
{
    /**
     * Show user generated pages based on slug
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index(Request $request)
    {
        $pageSlug = trim($request->page_slug);

        $pageData = Cache::rememberForever('pageData_' . $pageSlug , function() use ($pageSlug) {
            return Page::where('page_slug', $pageSlug)->firstOrFail();
        });

        return view('page.page', ['pageData' => $pageData]);
    }
}