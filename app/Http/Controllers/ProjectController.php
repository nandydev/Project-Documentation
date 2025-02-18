<?php

namespace App\Http\Controllers;

use OpenAI\Laravel\Facades\OpenAI;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Support\Facades\Response;


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

        public function generateDescriptions(Project $project): StreamedResponse
        {
            $this->authorize('update', $project);
        
            if (empty($project->images)) {
                return Response::stream(function () {
                    echo json_encode(['message' => 'No images found']);
                }, 400, [
                    'Content-Type' => 'application/json',
                    'X-Accel-Buffering' => 'no',
                    'Cache-Control' => 'no-cache',
                ]);
            }
        
            return Response::stream(function () use ($project) {
                $descriptions = [];
                
                foreach ($project->images as $index => $image) {
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
        
                        $description = $response['choices'][0]['message']['content'] ?? 'No description generated';
                        $descriptions[$image] = $description;
                        
                        // Stream the current progress
                        echo json_encode([
                            'progress' => [
                                'current' => $index + 1,
                                'total' => count($project->images),
                                'latest_description' => [
                                    'image' => $image,
                                    'description' => $description
                                ]
                            ]
                        ]) . "\n";
                        
                        ob_flush();
                        flush();
                        
                    } catch (\Exception $e) {
                        $descriptions[$image] = 'Error: ' . $e->getMessage();
                        echo json_encode(['error' => $e->getMessage()]) . "\n";
                        ob_flush();
                        flush();
                    }
                }
        
                // Update project with AI descriptions
                $project->update(['ai_descriptions' => $descriptions]);
                
                // Send final response
                echo json_encode([
                    'status' => 'completed',
                    'project' => $project->toArray()
                ]);
                
            }, 200, [
                'Content-Type' => 'application/x-ndjson',
                'X-Accel-Buffering' => 'no',
                'Cache-Control' => 'no-cache',
            ]);
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

        public function generateOverallDescription($id): StreamedResponse
        {
            return Response::stream(function () use ($id) {
                $project = Project::findOrFail($id);
                $imageDescriptions = $project->ai_descriptions;

                if (!$imageDescriptions) {
                    echo json_encode(['error' => 'No image descriptions found']) . "\n";
                    ob_flush();
                    flush();
                    return;
                }

                // Send initial processing status
                echo json_encode(['status' => 'processing', 'message' => 'Starting AI processing']) . "\n";
                ob_flush();
                flush();

                // Prepare the system message for better structured output
                $systemMessage = "You are an AI assistant that generates project descriptions. 
                    Please analyze the provided image descriptions and generate a comprehensive project description. 
                    Generate the description in 3-4 chunks, pausing after each chunk.";

                $prompt = "Based on these image descriptions, generate a detailed project description:\n" . 
                         implode("\n", $imageDescriptions);

                try {
                    $stream = OpenAI::chat()->createStreamed([
                        'model' => 'gpt-4o',
                        'messages' => [
                            ['role' => 'system', 'content' => $systemMessage],
                            ['role' => 'user', 'content' => $prompt]
                        ],
                        'temperature' => 0.7,
                        'max_tokens' => 1000,
                    ]);

                    $fullDescription = '';
                    $chunkBuffer = '';

                    foreach ($stream as $response) {
                        $content = $response->choices[0]->delta->content;
                        if ($content !== null) {
                            $chunkBuffer .= $content;
                            
                            // Send chunk when we have a complete sentence or significant content
                            if (str_ends_with(trim($content), '.') || strlen($chunkBuffer) > 100) {
                                echo json_encode([
                                    'status' => 'processing',
                                    'chunk' => $chunkBuffer,
                                    'message' => 'Generating description...'
                                ]) . "\n";
                                ob_flush();
                                flush();
                                
                                $fullDescription .= $chunkBuffer;
                                $chunkBuffer = '';
                            }
                        }
                    }

                    // Send any remaining content in the buffer
                    if (!empty($chunkBuffer)) {
                        $fullDescription .= $chunkBuffer;
                    }

                    // Update project with the full description
                    $project->description = $fullDescription;
                    $project->save();

                    // Send final response
                    echo json_encode([
                        'status' => 'completed',
                        'project_id' => $project->id,
                        'overall_description' => $fullDescription,
                        'message' => 'Description generation completed'
                    ]) . "\n";
                    ob_flush();
                    flush();

                } catch (\Exception $e) {
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Error generating description: ' . $e->getMessage()
                    ]) . "\n";
                    ob_flush();
                    flush();
                }
                
            }, 200, [
                'Content-Type' => 'application/x-ndjson',
                'X-Accel-Buffering' => 'no',
                'Cache-Control' => 'no-cache',
            ]);
        }


        public function generateApiDocumentation(Request $request, $id): StreamedResponse
        {
            return Response::stream(function () use ($request, $id) {
                $project = Project::findOrFail($id);
                $imageDescriptions = $request->input('image_descriptions', []);

                if (empty($imageDescriptions)) {
                    echo json_encode(['error' => 'No image descriptions provided']);
                    return;
                }

                echo json_encode(['status' => 'starting']) . "\n";
                ob_flush();
                flush();

                $prompt = "Generate comprehensive API documentation for the following endpoints based on these image descriptions:\n" . 
                implode("\n", $imageDescriptions) . 
                "\n\nInclude for each endpoint:\n" .
                "- Endpoint URL\n" .
                "- HTTP Method\n" .
                "- Request Parameters with examples\n" .
                "- Response Format (success and error cases)\n" .
                "- Possible Status Codes\n" .
                "- Example Curl request\n";

                echo json_encode(['status' => 'generating']) . "\n";
                ob_flush();
                flush();

                $response = OpenAI::chat()->create([
                    'model' => 'gpt-4o',
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ]);

                $markdown = $response['choices'][0]['message']['content'];
                \Storage::put("api_docs/project_{$project->id}.md", $markdown);

                echo json_encode([
                    'status' => 'completed',
                    'project_id' => $project->id,
                    'documentation' => $markdown,
                ]);
                
            }, 200, [
                'Content-Type' => 'application/x-ndjson',
                'X-Accel-Buffering' => 'no',
                'Cache-Control' => 'no-cache',
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
        
        
        public function updateProjectImages(Request $request, Project $project): JsonResponse
        {
            $this->authorize('update', $project);

            $request->validate([
                'images' => 'required|array',
                'images.*.id' => 'required|exists:images,id',
                'images.*.description' => 'nullable|string',
                'images.*.order' => 'required|integer|min:0',
            ]);

            try {
                \DB::beginTransaction();

                // Update each image
                foreach ($request->images as $imageData) {
                    $image = Image::findOrFail($imageData['id']);
                    
                    // Verify the image belongs to this project
                    if ($image->project_id !== $project->id) {
                        throw new \Exception('Invalid image ID for this project');
                    }

                    $image->update([
                        'description' => $imageData['description'],
                        'order' => $imageData['order']
                    ]);
                }

                // Get all project images ordered by the new order
                $orderedImages = $project->images()
                    ->orderBy('order')
                    ->get();

                // Reindex orders to ensure they are sequential
                foreach ($orderedImages->values() as $index => $image) {
                    $image->update(['order' => $index + 1]);
                }

                // Combine descriptions into project description
                $combinedDescription = $orderedImages
                    ->map(fn($image) => $image->description)
                    ->filter()
                    ->join("\n\n");

                $project->update(['description' => $combinedDescription]);

                \DB::commit();

                return response()->json([
                    'message' => 'Images updated successfully',
                    'project' => $project->load('images')
                ]);

            } catch (\Exception $e) {
                \DB::rollBack();
                return response()->json([
                    'message' => 'Failed to update images',
                    'error' => $e->getMessage()
                ], 500);
            }
        }
        
    }
