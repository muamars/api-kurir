<?php

namespace App\Http\Controllers;

use App\Http\Requests\Blog\StoreBlogRequest;
use App\Http\Requests\Blog\UpdateBlogRequest;
use App\Models\Blog;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

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
        $user = auth()->user();
        $userRole = $user->roles->first()->name ?? 'User';
        
        // Menggunakan scope untuk filter berdasarkan role
        $blogs = Blog::with('user')
            ->forRole($userRole)
            ->latest()
            ->paginate(10);

        return response()->json($blogs);
    }

    public function apiStore(StoreBlogRequest $request)
    {
        $validated = $request->validated();
        $validated['slug'] = Str::slug($validated['title']);
        $validated['user_id'] = auth()->id();

        // Handle image upload
        if ($request->hasFile('image')) {
            try {
                $image = $request->file('image');
                $filename = 'blog_'.time().'_'.uniqid().'.jpg'; // Force JPG for better compression

                // Use compression method
                $compressedPaths = $this->storeCompressedBlogImage($image, 'blog-images', $filename);
                $validated['image_url'] = $compressedPaths['original'];
                $validated['image_thumbnail'] = $compressedPaths['thumbnail'];
            } catch (\Exception $e) {
                \Log::error('Blog image upload failed', [
                    'error' => $e->getMessage(),
                    'file' => $image->getClientOriginalName() ?? 'unknown',
                ]);
                return response()->json([
                    'message' => 'Failed to upload image: '.$e->getMessage()
                ], 500);
            }
        }

        $blog = Blog::create($validated);

        return response()->json($blog->load('user'), 201);
    }

    public function apiShow(Blog $blog)
    {
        $user = auth()->user();
        
        // Cek apakah user bisa melihat blog ini berdasarkan target audience
        if (!$user->hasRole('Admin')) {
            $allowedAudiences = ['all'];
            
            if ($user->hasRole('Kurir')) {
                $allowedAudiences[] = 'kurir';
            } else {
                $allowedAudiences[] = 'user';
            }
            
            if (!in_array($blog->target_audience, $allowedAudiences)) {
                return response()->json([
                    'message' => 'Blog ini tidak tersedia untuk role Anda.'
                ], 403);
            }
        }
        
        return response()->json($blog->load('user'));
    }

    public function apiUpdate(UpdateBlogRequest $request, Blog $blog)
    {
        $validated = $request->validated();
        $validated['slug'] = Str::slug($validated['title']);

        // Handle image upload
        if ($request->hasFile('image')) {
            try {
                // Delete old images if exist
                if ($blog->image_url) {
                    Storage::delete($blog->image_url);
                }
                if ($blog->image_thumbnail) {
                    Storage::delete($blog->image_thumbnail);
                }

                $image = $request->file('image');
                $filename = 'blog_'.time().'_'.uniqid().'.jpg'; // Force JPG for better compression

                // Use compression method
                $compressedPaths = $this->storeCompressedBlogImage($image, 'blog-images', $filename);
                $validated['image_url'] = $compressedPaths['original'];
                $validated['image_thumbnail'] = $compressedPaths['thumbnail'];
            } catch (\Exception $e) {
                \Log::error('Blog image upload failed', [
                    'error' => $e->getMessage(),
                    'file' => $image->getClientOriginalName() ?? 'unknown',
                ]);
                return response()->json([
                    'message' => 'Failed to upload image: '.$e->getMessage()
                ], 500);
            }
        }

        $blog->update($validated);

        return response()->json($blog->load('user'));
    }

    public function apiDestroy(Blog $blog)
    {
        // Delete associated images if exist
        if ($blog->image_url) {
            Storage::delete($blog->image_url);
        }
        if ($blog->image_thumbnail) {
            Storage::delete($blog->image_thumbnail);
        }

        $blog->delete();

        return response()->json(['message' => 'Blog deleted successfully']);
    }

    /**
     * Store compressed blog image with thumbnail generation
     */
    private function storeCompressedBlogImage($file, string $directory, string $filename): array
    {
        $manager = ImageManager::gd();
        $image = $manager->read($file);

        // Get original dimensions for smart compression
        $originalWidth = $image->width();
        $originalHeight = $image->height();

        // Compress and store original
        $originalPath = $directory.'/'.$filename;
        $compressedImage = $this->compressBlogImage($image, $originalWidth, $originalHeight);
        $encodedImage = $compressedImage->toJpeg($this->getBlogCompressionQuality($originalWidth, $originalHeight));
        
        Storage::put($originalPath, $encodedImage);

        // Generate and store thumbnail (400x300px for blog)
        $thumbnailPath = $directory.'/thumbnails/thumb_'.$filename;
        $thumbnail = $image->resize(400, 300, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
        $thumbnailEncoded = $thumbnail->toJpeg(85); // Good quality for thumbnails
        
        Storage::put($thumbnailPath, $thumbnailEncoded);

        return [
            'original' => $originalPath,
            'thumbnail' => $thumbnailPath,
        ];
    }

    /**
     * Compress blog image based on dimensions
     */
    private function compressBlogImage($image, int $width, int $height)
    {
        // Resize if too large (max 1920x1080 for blog images)
        if ($width > 1920 || $height > 1080) {
            $image = $image->resize(1920, 1080, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }

        return $image;
    }

    /**
     * Get compression quality based on image dimensions
     */
    private function getBlogCompressionQuality(int $width, int $height): int
    {
        $totalPixels = $width * $height;

        // Smart compression based on image size
        if ($totalPixels > 2000000) { // > 2MP
            return 75; // Higher compression for large images
        } elseif ($totalPixels > 1000000) { // > 1MP
            return 80; // Medium compression
        } else {
            return 85; // Lower compression for smaller images
        }
    }
}
