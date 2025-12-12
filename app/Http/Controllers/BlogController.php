<?php

namespace App\Http\Controllers;

use App\Http\Requests\Blog\StoreBlogRequest;
use App\Http\Requests\Blog\UpdateBlogRequest;
use App\Models\Blog;
use Illuminate\Support\Str;

class BlogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view blogs')->only(['index', 'show']);
        $this->middleware('permission:create blogs')->only(['create', 'store']);
        $this->middleware('permission:edit blogs')->only(['edit', 'update']);
        $this->middleware('permission:delete blogs')->only(['destroy']);
    }

    public function index()
    {
        $blogs = Blog::with('user')->latest()->paginate(10);

        return view('blogs.index', compact('blogs'));
    }

    public function create()
    {
        return view('blogs.create');
    }

    public function store(StoreBlogRequest $request)
    {
        $validated = $request->validated();
        $validated['slug'] = Str::slug($validated['title']);
        $validated['user_id'] = auth()->id();

        Blog::create($validated);

        return redirect()->route('blogs.index')->with('success', 'Blog created successfully!');
    }

    public function show(Blog $blog)
    {
        return view('blogs.show', compact('blog'));
    }

    public function edit(Blog $blog)
    {
        return view('blogs.edit', compact('blog'));
    }

    public function update(UpdateBlogRequest $request, Blog $blog)
    {
        $validated = $request->validated();
        $validated['slug'] = Str::slug($validated['title']);

        $blog->update($validated);

        return redirect()->route('blogs.index')->with('success', 'Blog updated successfully!');
    }

    public function destroy(Blog $blog)
    {
        $blog->delete();

        return redirect()->route('blogs.index')->with('success', 'Blog deleted successfully!');
    }

    // API Methods
    public function apiIndex()
    {
        $blogs = Blog::with('user')->latest()->paginate(10);

        return response()->json($blogs);
    }

    public function apiStore(StoreBlogRequest $request)
    {
        $validated = $request->validated();
        $validated['slug'] = Str::slug($validated['title']);
        $validated['user_id'] = auth()->id();

        $blog = Blog::create($validated);

        return response()->json($blog->load('user'), 201);
    }

    public function apiShow(Blog $blog)
    {
        return response()->json($blog->load('user'));
    }

    public function apiUpdate(UpdateBlogRequest $request, Blog $blog)
    {
        $validated = $request->validated();
        $validated['slug'] = Str::slug($validated['title']);
        $blog->update($validated);

        return response()->json($blog->load('user'));
    }

    public function apiDestroy(Blog $blog)
    {
        $blog->delete();

        return response()->json(['message' => 'Blog deleted successfully']);
    }
}
