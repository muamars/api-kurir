<?php

namespace App\Http\Controllers;

use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Models\Project;

class ProjectController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view projects')->only(['index', 'show']);
        $this->middleware('permission:create projects')->only(['create', 'store']);
        $this->middleware('permission:edit projects')->only(['edit', 'update']);
        $this->middleware('permission:delete projects')->only(['destroy']);
    }

    public function index()
    {
        $projects = Project::with('user')->latest()->paginate(10);

        return view('projects.index', compact('projects'));
    }

    public function create()
    {
        return view('projects.create');
    }

    public function store(StoreProjectRequest $request)
    {
        $validated = $request->validated();
        $validated['user_id'] = auth()->id();

        Project::create($validated);

        return redirect()->route('projects.index')->with('success', 'Project created successfully!');
    }

    public function show(Project $project)
    {
        return view('projects.show', compact('project'));
    }

    public function edit(Project $project)
    {
        return view('projects.edit', compact('project'));
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {
        $validated = $request->validated();

        $project->update($validated);

        return redirect()->route('projects.index')->with('success', 'Project updated successfully!');
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('projects.index')->with('success', 'Project deleted successfully!');
    }

    // API Methods
    public function apiIndex()
    {
        $projects = Project::with('user')->latest()->paginate(10);

        return response()->json($projects);
    }

    public function apiStore(StoreProjectRequest $request)
    {
        $validated = $request->validated();
        $validated['user_id'] = auth()->id();

        $project = Project::create($validated);

        return response()->json($project->load('user'), 201);
    }

    public function apiShow(Project $project)
    {
        return response()->json($project->load('user'));
    }

    public function apiUpdate(UpdateProjectRequest $request, Project $project)
    {
        $validated = $request->validated();

        $project->update($validated);

        return response()->json($project->load('user'));
    }

    public function apiDestroy(Project $project)
    {
        $project->delete();

        return response()->json(['message' => 'Project deleted successfully']);
    }
}
