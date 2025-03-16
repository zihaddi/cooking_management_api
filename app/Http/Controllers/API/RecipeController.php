<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Recipe;
use App\Models\RecipeImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class RecipeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }

    public function index(Request $request)
    {
        $query = Recipe::with('images');

        // Filter by difficulty if provided
        if ($request->has('difficulty')) {
            $query->where('difficulty_level', $request->difficulty);
        }

        // Search by name if provided
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name_en', 'like', "%{$search}%")
                  ->orWhere('name_bn', 'like', "%{$search}%");
            });
        }

        $recipes = $query->paginate(12);

        return response()->json([
            'success' => true,
            'message' => 'Recipes retrieved successfully',
            'data' => $recipes
        ], 200);
    }

    public function store(Request $request)
    {
        // Check if user has permission
        if (!$request->user()->can('create recipes')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name_en' => 'required|string|max:255',
            'name_bn' => 'nullable|string|max:255',
            'description_en' => 'required|string',
            'description_bn' => 'nullable|string',
            'ingredients' => 'required|array',
            'ingredients.*.name' => 'required|string',
            'ingredients.*.quantity' => 'required|string',
            'instructions' => 'required|array',
            'instructions.*' => 'required|string',
            'preparation_time' => 'required|integer|min:1',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $recipe = Recipe::create([
            'name_en' => $request->name_en,
            'name_bn' => $request->name_bn,
            'description_en' => $request->description_en,
            'description_bn' => $request->description_bn,
            'ingredients' => $request->ingredients,
            'instructions' => $request->instructions,
            'preparation_time' => $request->preparation_time,
            'difficulty_level' => $request->difficulty_level,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Recipe created successfully',
            'data' => $recipe
        ], 201);
    }

    public function show($id)
    {
        $recipe = Recipe::with('images')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Recipe retrieved successfully',
            'data' => $recipe
        ], 200);
    }

    public function update(Request $request, $id)
    {
        // Check if user has permission
        if (!$request->user()->can('edit recipes')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $recipe = Recipe::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name_en' => 'sometimes|required|string|max:255',
            'name_bn' => 'nullable|string|max:255',
            'description_en' => 'sometimes|required|string',
            'description_bn' => 'nullable|string',
            'ingredients' => 'sometimes|required|array',
            'ingredients.*.name' => 'required|string',
            'ingredients.*.quantity' => 'required|string',
            'instructions' => 'sometimes|required|array',
            'instructions.*' => 'required|string',
            'preparation_time' => 'sometimes|required|integer|min:1',
            'difficulty_level' => 'sometimes|required|in:beginner,intermediate,advanced',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $recipe->fill($request->only([
            'name_en', 'name_bn', 'description_en', 'description_bn',
            'preparation_time', 'difficulty_level'
        ]));

        if ($request->has('ingredients')) {
            $recipe->ingredients = $request->ingredients;
        }

        if ($request->has('instructions')) {
            $recipe->instructions = $request->instructions;
        }

        $recipe->save();

        return response()->json([
            'success' => true,
            'message' => 'Recipe updated successfully',
            'data' => $recipe
        ], 200);
    }

    public function destroy(Request $request, $id)
    {
        // Check if user has permission
        if (!$request->user()->can('delete recipes')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $recipe = Recipe::findOrFail($id);
        
        // Delete all associated images
        foreach ($recipe->images as $image) {
            Storage::disk('public')->delete($image->image_path);
            $image->delete();
        }

        $recipe->delete();

        return response()->json([
            'success' => true,
            'message' => 'Recipe deleted successfully',
        ], 200);
    }

    public function uploadImage(Request $request, $id)
    {
        // Check if user has permission
        if (!$request->user()->can('edit recipes')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg|max:5120',
            'is_primary' => 'sometimes|boolean',
            'display_order' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $recipe = Recipe::findOrFail($id);
        
        $image = $request->file('image');
        $filename = Str::random(20) . '.' . $image->getClientOriginalExtension();
        $imagePath = $image->storeAs('recipes/images', $filename, 'public');

        $isPrimary = $request->has('is_primary') ? $request->is_primary : false;
        
        // If setting this as primary, reset all other images to non-primary
        if ($isPrimary) {
            $recipe->images()->update(['is_primary' => false]);
        }
        
        // If it's the first image and no primary status specified, make it primary
        if ($recipe->images->count() === 0 && !$request->has('is_primary')) {
            $isPrimary = true;
        }

        $displayOrder = $request->has('display_order') ? $request->display_order : $recipe->images->count();

        $recipeImage = RecipeImage::create([
            'recipe_id' => $recipe->id,
            'image_path' => $imagePath,
            'is_primary' => $isPrimary,
            'display_order' => $displayOrder,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Image uploaded successfully',
            'data' => $recipeImage
        ], 201);
    }
}