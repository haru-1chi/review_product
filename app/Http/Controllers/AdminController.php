<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Review;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Http\Middleware\Admin;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('admin');
    }
    //user
    public function delete_user($id)
    {
        try {
            $user = User::withTrashed()->where('id', $id)->first();
            if ($user) {
                $user->delete();
                return response()->json(['success' => true, 'message' => 'User deleted successfully'], 201);
            } else {
                return response()->json(['success' => false, 'error' => 'User not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function getList_user(Request $request)
    {
        try {
            $query = User::query();
            $filters = $request->only(['name']);
            foreach ($filters as $key => $value) {
                $query->where($key, 'like', "%$value%");
            }
            $user_data = $query->get();
            $records = [];
            $records = $user_data->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'picture' => $user->picture
                ];
            });
            return response()->json(['success' => true, 'List of users' => $records], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function getDetail_user($id)
    {
        try {
            $user = User::find($id);
            $age = $this->calculateAge($user->birthday);
            $record = [
                'id' => $user->id,
                'name' => $user->name,
                'gender' => $user->gender,
                'age' => $age,
                'birthday' => $user->birthday,
                'phone' => $user->phone,
                'email' => $user->email,
                'picture' => $user->picture
            ];
            return response()->json(['success' => true, 'Detail of User' => $record], 200); //
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400); //
        }
    }
    private function calculateAge($birthday)
    {
        $birthdate = Carbon::parse($birthday);
        $currentDate = Carbon::now();
        $age = $currentDate->diff($birthdate)->y;
        return $age;
    }

    //product
    public function insert_product(Request $request)
    {
        try {
            $validated_data = $request->validate([
                'name' => 'required|max:50',
                'category' => 'required',
                'description' => 'required',
                'price' => 'required',
                'picture' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
            ]);
            $product = Product::create($validated_data);
            if ($request->hasFile('picture')) {
                $picturePath = $request->file('picture')->store('public/pictures');
                $fullUrlPath = asset(Storage::url($picturePath));
                $product->update(['picture' => $fullUrlPath]);
            }
            return response()->json(['success' => true, 'message' => 'Product insert successfully'], 201);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function delete_product($id)
    {
        try {
            $product = Product::withTrashed()->where('id', $id)->first();
            if ($product) {
                $product->delete();
                return response()->json(['success' => true, 'message' => 'Product deleted successfully'], 201);
            } else {
                return response()->json(['success' => false, 'error' => 'Product not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    public function update_product(Request $request, $id)
    {
        try {
            $validated_data = $request->validate([
                'name' => 'sometimes|required|max:50',
                'category' => 'sometimes|required',
                'description' => 'sometimes|required',
                'price' => 'sometimes|required',
                'picture' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
            ]);
            $product = Product::where('id', $id)->first();
            if ($product) {
                $product->update($validated_data);
                if ($request->hasFile('picture')) {
                    if ($product->picture) {
                        Storage::delete($product->picture);
                    }
                    $picturePath = $request->file('picture')->store('public/pictures');
                    $fullUrlPath = asset(Storage::url($picturePath));
                    $product->update(['picture' => $fullUrlPath]);
                }
                return response()->json(['success' => true, 'message' => 'Product updated successfully'], 201);
            } else {
                return response()->json(['success' => false, 'error' => 'Product not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function getList_product(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'user must to login'], 401);
            }

            $query = Product::query();
            $filters = $request->only(['name', 'category', 'price']);

            foreach ($filters as $key => $value) {
                $query->where($key, 'like', "%$value%");
            }
            $product_data = $query->get();
            $records = $product_data->map(function ($product) {
                $averageRating = $this->average_rating($product->id);
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category,
                    'price' => $product->price,
                    'picture' => $product->picture,
                    'average_rating' => $averageRating,
                ];
            });

            return response()->json(['success' => true, 'List of Products' => $records], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
    public function getDetail_product($product_id)
    {
        try {
            $reviews = Review::with([
                'users' => function ($query) {
                    $query->withTrashed();
                },
                'products' => function ($query) {
                    $query->withTrashed();
                }
            ])->where('product_id', $product_id)->get();
            if ($reviews->isEmpty()) {
                return response()->json(['success' => false, 'error' => 'No reviews found for this product'], 404);
            }
            $productDetails = [
                'id' => optional($reviews->first()->products)->id,
                'product_name' => optional($reviews->first()->products)->name,
                'category' => optional($reviews->first()->products)->category,
                'description' => optional($reviews->first()->products)->description,
                'price' => optional($reviews->first()->products)->price,
                'picture' => optional($reviews->first()->products)->picture,
            ];
            $reviewDetails = [];
            foreach ($reviews as $review) {
                $reviewDetails[] = [
                    'user_id' => optional($review->users)->id,
                    'user_name' => optional($review->users)->name,
                    'review_id' => $review->id,
                    'rating' => $review->rating,
                    'review_text' => $review->review_text
                ];
            }
            $reviewProduct = collect($reviewDetails)->groupBy('user_id')->map(function ($group) {
                return [
                    'user_id' => $group->first()['user_id'],
                    'user_name' => $group->first()['user_name'],
                    'reviews' => $group->map(function ($review) {
                        unset ($review['user_id']);
                        unset ($review['user_name']);
                        return $review;
                    })->values()->all(),
                ];
            })->values()->all();
            $averageRating = $this->average_rating($product_id);
            $record = [
                'success' => true,
                'Detail of Product' => $productDetails,
                'average_rating' => $averageRating,
                'review_product' => $reviewProduct
            ];
            return response()->json($record, 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
    public function average_rating($productId)
    {
            $reviews = Review::where('product_id', $productId)->get();
            if ($reviews->isEmpty()) {
                return 0;
            }
            $ratings = $reviews->pluck('rating')->toArray();
            $averageRating = array_sum($ratings)/count($ratings);
            return $averageRating;
    }
}
