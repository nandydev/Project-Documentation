<?php

namespace App\Http\Controllers;

use OpenAI\Laravel\Facades\OpenAI;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;


class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = $request->user()->projects()->latest()->paginate(10);
        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'project_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'images.*' => ['image', 'mimes:jpg,png,webp', 'max:2048'],
            'images' => ['array', 'max:20'],
        ]);

        $project = $request->user()->projects()->create([
            'project_name' => $request->project_name,
            'description' => $request->description,
        ]);

        if ($request->hasFile('images')) {
            $images = [];
            foreach ($request->file('images') as $image) {
                $path = $image->store("uploads/projects/{$project->id}", 'public');
                $images[] = $path;
            }
            $project->update(['images' => $images]);
        }

        return response()->json($project, 201);
    }

    public function show(Project $project): JsonResponse
    {
        $this->authorize('view', $project);
        return response()->json($project);
    }

    public function update(Request $request, $id)
    {
        // Find the project using the provided ID
        $project = Project::find($id);
        
        if (!$project) {
            return response()->json(['error' => 'Project not found'], 404);
        }

        try {
            $validated = $request->validate([
                'project_name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'images.*' => ['image', 'mimes:jpg,png,webp', 'max:2048'],
                'images' => ['array', 'max:20'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['error' => 'Validation failed', 'details' => $e->errors()], 422);
        }

        // Update Project Data
        $project->update([
            'project_name' => $validated['project_name'],
            'description' => $validated['description'] ?? null,
        ]);

        // Handle Image Uploads
        if ($request->hasFile('images')) {
            foreach ($project->images ?? [] as $oldImage) {
                Storage::disk('public')->delete($oldImage);
            }

            $images = [];
            foreach ($request->file('images') as $image) {
                $path = $image->store("uploads/projects/{$project->id}", 'public');
                $images[] = $path;
            }

            $project->update(['images' => $images]);
        }

        return response()->json([
            'message' => 'Project updated successfully',
            'project' => $project,
        ], 200);
    }
  
    
    public function destroy(Project $project): JsonResponse
    {
        $this->authorize('delete', $project);

        foreach ($project->images ?? [] as $image) {
            Storage::disk('public')->delete($image);
        }

        Storage::disk('public')->deleteDirectory("uploads/projects/{$project->id}");

        $project->delete();
        
        return response()->json([
            'message' => 'Project deleted successfully',
        ], 200);
    }

    public function generateDescriptions(Project $project): JsonResponse
    {
        $this->authorize('update', $project);
    
        if (empty($project->images)) {
            return response()->json(['message' => 'No images found'], 400);
        }
    
        $descriptions = [];
        foreach ($project->images as $image) {
            try {
                // Read and encode the image in base64
                $imagePath = Storage::disk('public')->path($image);
                $imageContent = base64_encode(file_get_contents($imagePath));
    
                // Make OpenAI API request
                $response = OpenAI::chat()->create([
                    'model' => 'gpt-4o', // Use GPT-4o (multimodal)
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => [
                                ['type' => 'text', 'text' => 'What is in this image?'],
                                ['type' => 'image_url', 'image_url' => ['url' => "data:image/jpeg;base64,{$imageContent}"]],
                            ],
                        ],
                    ],
                ]);
    
                // Extract description from response
                $descriptions[$image] = $response['choices'][0]['message']['content'] ?? 'No description generated';
            } catch (\Exception $e) {
                $descriptions[$image] = 'Error: ' . $e->getMessage();
            }
        }
    
        // Update project with AI descriptions
        $project->update(['ai_descriptions' => $descriptions]);
    
        return response()->json($project);
    }
    

    public function editDescriptions(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);
    
        $request->validate([
            'descriptions' => ['required', 'array'],
            'descriptions.*' => ['nullable', 'string'],
        ]);
    
        $descriptions = $request->descriptions;
    
        foreach ($project->images as $image) {
            if (empty($descriptions[$image])) {
                try {
                    // Read and encode the image in base64
                    $imagePath = Storage::disk('public')->path($image);
                    $imageContent = base64_encode(file_get_contents($imagePath));
    
                    // Make OpenAI API request
                    $response = OpenAI::chat()->create([
                        'model' => 'gpt-4o',
                        'messages' => [
                            [
                                'role' => 'user',
                                'content' => [
                                    ['type' => 'text', 'text' => 'What is in this image?'],
                                    ['type' => 'image_url', 'image_url' => ['url' => "data:image/jpeg;base64,{$imageContent}"]],
                                ],
                            ],
                        ],
                    ]);
    
                    // Extract AI-generated description
                    $descriptions[$image] = $response['choices'][0]['message']['content'] ?? 'No description generated';
                } catch (\Exception $e) {
                    $descriptions[$image] = 'Error: ' . $e->getMessage();
                }
            }
        }
    
        // Update project descriptions
        $project->update(['ai_descriptions' => $descriptions]);
    
        return response()->json($project);
    }

    public function generateOverallDescription($id)
    {
        $project = Project::findOrFail($id);
        
        $imageDescriptions = $project->ai_descriptions;

        if (!$imageDescriptions) {
            return response()->json(['error' => 'No image descriptions found'], 404);
        }

        $prompt = "Summarize the following image descriptions into an overall project description:\n" . implode("\n", $imageDescriptions);
        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $project->description = $response['choices'][0]['message']['content'];
        $project->save();

        return response()->json([
            'project_id' => $project->id,
            'overall_description' => $project->description,
        ]);
    }


    public function generateApiDocumentation(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $imageDescriptions = $request->input('image_descriptions', []);
        //dd($imageDescriptions);
        if (empty($imageDescriptions)) {
            return response()->json(['error' => 'No image descriptions provided'], 422);
        }

        $prompt = "Generate comprehensive API documentation for the following endpoints based on these image descriptions:\n" . 
          implode("\n", $imageDescriptions) . 
          "\n\nInclude for each endpoint:\n" .
          "- Endpoint URL\n" .
          "- HTTP Method\n" .
          "- Request Parameters with examples\n" .
          "- Response Format (success and error cases)\n" .
          "- Possible Status Codes\n" .
          "- Example Curl request\n";

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ]);

        $markdown = $response['choices'][0]['message']['content'];
        \Storage::put("api_docs/project_{$project->id}.md", $markdown);

        return response()->json([
            'project_id' => $project->id,
            'documentation' => $markdown,
        ]);
    }

    
    public function previewApiDocumentation($id)
    {
        $path = "api_docs/project_{$id}.md";
        if (!\Storage::exists($path)) {
            return response()->json(['error' => 'Documentation not found'], 404);
        }

        $markdown = \Storage::get($path);

        return response()->json([
            'project_id' => $id,
            'documentation' => $markdown,
        ]);
    }
    
    
}
