<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Review;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class userController extends Controller
{
    //user
    public function update_user(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'user must to login'], 401);
            }
            $validated_data = $request->validate([
                'name' => 'sometimes|required|max:50',
                'gender' => 'sometimes|required',
                'birthday' => 'sometimes|required',
                'phone' => 'sometimes|required',
                'email' => 'sometimes|required|email',
                'role' => 'sometimes|required',
                'picture' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:2048'
            ]);
            $user = User::find($user->id);
            if ($user) {
                $user->update($validated_data);
                if ($request->hasFile('picture')) {
                    if ($user->picture) {
                        Storage::delete($user->picture);
                    }
                    $picturePath = $request->file('picture')->store('public/pictures');
                    $fullUrlPath = asset(Storage::url($picturePath));
                    $user->update(['picture' => $fullUrlPath]);
                }
                return response()->json(['success' => true, 'message' => 'User updated successfully'], 201);
            } else {
                return response()->json(['success' => false, 'error' => 'User not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function getDetail_user()
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'user must to login'], 401);
            }
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

    //Product
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
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'user must to login'], 401);
            }
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

    public function review(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'user must to login before reviewing'], 401);
            }
            $validated_data = $request->validate([
                'product_id' => 'required|exists:products,id',
                'rating' => 'required',
                'review_text' => 'nullable|max:100'
            ]);
            $now = now();
            $review_data = array_merge($validated_data, [
                'user_id' => $user->id,
                'created_at' => $now,
                'updated_at' => $now
            ]);
            DB::table('reviews')->insert($review_data);
            return response()->json([
                'message' => 'review product succesefully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }
    public function delete_review($id)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'user must to login'], 401);
            }
            $review = Review::withTrashed()->where('id', $id)->first();
            if ($review) {
                $review->delete();
                return response()->json(['success' => true, 'message' => 'Review deleted successfully'], 201);
            } else {
                return response()->json(['success' => false, 'error' => 'Review_id not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function update_review(Request $request, $id)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'user must to login'], 401);
            }
            $validated_data = $request->validate([
                'rating' => 'sometimes|required',
                'review_text' => 'sometimes|nullable|max:100'
            ]);
            $review = Review::where('id', $id)->first();
            if ($review) {
                $review->update($validated_data);
                return response()->json(['success' => true, 'message' => 'Review changed successfully'], 201);
            } else {
                return response()->json(['success' => false, 'error' => 'Review not found'], 404);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
    public function getHistory_review(Request $request)
    {
        try {
            $user = auth()->user();
            if (!$user) {
                return response()->json(['error' => 'User must be logged in'], 401);
            }
            $filters = $request->only(['product_id']);
            $query = Review::with([
                'products' => function ($query) {
                    $query->withTrashed();
                }
            ])->where('user_id', $user->id);
            foreach ($filters as $key => $value) {
                if ($value) {
                    if ($key === 'product_id') {
                        $query->where('product_id', $value);
                    } else {
                        $query->where($key, $value);
                    }
                }
            }
            $query->whereNull('deleted_at')->orderBy('created_at', 'asc');
            $reviews = $query->get();
            $reviewDetails = [];
            foreach ($reviews as $review) {
                $reviewDetails[] = [
                    'product_id' => optional($review->products)->id,
                    'product_name' => optional($review->products)->name,
                    'review_id' => $review->id,
                    'rating' => $review->rating,
                    'review_text' => $review->review_text
                ];
            }
            $reviewProduct = collect($reviewDetails)->groupBy('product_id')->map(function ($group) {
                return [
                    'product_id' => $group->first()['product_id'],
                    'product_name' => $group->first()['product_name'],
                    'reviews' => $group->map(function ($review) {
                        unset ($review['product_id']);
                        unset ($review['product_name']);
                        return $review;
                    })->values()->all(),
                ];
            })->values()->all();
            $userReviews = [
                'id' => optional($reviews->first()->users)->id,
                'product_name' => optional($reviews->first()->users)->name,
            ];
            $record = [
                'success' => true,
                'Detail of Product' => $userReviews,
                'review_product' => $reviewProduct
            ];
            return response()->json($record, 200);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function average_rating($productId)
    {
        try {
            $reviews = Review::where('product_id', $productId)->get();
            if ($reviews->isEmpty()) {
                return 0;
            }
            $ratings = $reviews->pluck('rating')->toArray();
            $averageRating = array_sum($ratings) / count($ratings);
            return $averageRating;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function calculateAge($birthday)
    {
        $birthdate = Carbon::parse($birthday);
        $currentDate = Carbon::now();
        $age = $currentDate->diffInYears($birthdate);
        return $age;
    }
}
