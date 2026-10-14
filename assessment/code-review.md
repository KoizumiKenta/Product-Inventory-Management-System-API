# Code review: OrderController

Thanks for putting this up. The structure is readable and the three actions do what they say,
so the shape is right. There is a fair bit to change before this can go in though, mostly
around validation, the queries, and what happens when something is missing.

I have split the feedback into what blocks the merge and what can follow.

## Blocking

### 1. `index()` loads the whole orders table

```php
$orders = Order::all();
```

This pulls every row into memory and builds a PHP array from all of them. It will be fine on
your dev database and will fall over in production the first time the table gets big. Paginate
it. If a client genuinely needs everything, make that an explicit export endpoint with chunking.

### 2. N+1 query in the loop

```php
foreach ($orders as $order) {
    $user = User::find($order->user_id);
```

One query per order. 500 orders means 501 queries. Define the relationship on the model and
eager load it:

```php
// Order.php
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}

// controller
$orders = Order::with('user')->paginate(25);
```

Then read `$order->user->name` instead of looking the user up by hand. Worth turning on
`Model::preventLazyLoading()` in a non-production environment so this class of bug throws
during development instead of quietly costing you query time.

### 3. `store()` has no validation at all

```php
$order->user_id = $request->user_id;
$order->total   = $request->total;
$order->status  = $request->status;
```

Nothing checks that `user_id` exists, that `total` is numeric and non-negative, or that `status`
is one of the statuses we actually support. A request with no body creates a row of nulls, and
`total` can arrive as a negative number or a string. This needs a Form Request:

```php
public function rules(): array
{
    return [
        'user_id' => ['required', 'exists:users,id'],
        'total'   => ['required', 'numeric', 'min:0'],
    ];
}
```

### 4. `status` must not come from the request

This is the one I would push back on hardest. The client is choosing the order status, so
anyone who can create an order can create it already marked as paid or shipped. Status belongs
to the server:

```php
$order = Order::create($request->validated() + ['status' => Order::STATUS_PENDING]);
```

Transitions after that should go through an explicit action, not a general update.

On a related note, `user_id` coming from the request is worth questioning too. If an order
always belongs to the caller, take it from `$request->user()->id` and drop it from the payload
entirely. Otherwise any authenticated user can create orders in someone else's name.

### 5. Raw SQL built by string concatenation

```php
\DB::select("SELECT * FROM audit_log WHERE order_id = " . $order->id);
```

`$order->id` happens to be a safe integer today, so this specific line is not exploitable. The
pattern is the problem: the next person who edits it will reach for `$request->something` and
then it is. Always bind:

```php
DB::select('select * from audit_log where order_id = ?', [$order->id]);
```

### 6. That query does nothing

The result is not assigned or returned. Either the intent was to write an audit entry and the
statement is wrong, or this is leftover debugging and should be deleted. If it is meant to be
an audit trail, it should be an insert (ideally via a model or an event listener), and it should
be in a transaction with the order insert so you cannot end up with one and not the other.

### 7. `destroy()` fatals on a missing id

```php
$order = Order::find($id);
$order->delete();
```

`find()` returns null when the id does not exist, so this throws a fatal error and the client
gets a 500 where it should get a 404. Use route model binding:

```php
public function destroy(Order $order): JsonResponse
```

Laravel then resolves it or 404s for you, and the method gets shorter.

### 8. Nothing is authorised

Any authenticated user can list every order in the system and delete any of them by id. There
is no policy, no ownership check, no middleware. Add an `OrderPolicy` and call
`$this->authorize('delete', $order)`, or register it with `authorizeResource()` in the
constructor.

## Worth fixing

**`$user->name` assumes the user is there.** Even with eager loading, if the user row was
deleted the relation is null and this throws. `$order->user?->name` at minimum.

**Shape the response with a Resource.** Building the array by hand in the controller means the
format is duplicated the moment a second endpoint returns an order. An `OrderResource` gives you
one place to change it.

**Status codes.** `store()` returns 200 for a created resource, it should be 201. `destroy()`
returning `{"message": "Deleted"}` is fine, though a 204 with no body is more usual for a delete.

**Import the facade.** `\DB::` works but `use Illuminate\Support\Facades\DB;` at the top is the
convention in the rest of the codebase.

**Return types.** None of the three methods declare one. `: JsonResponse` on each makes the
contract obvious and lets static analysis help.

**Unused parameter.** `index(Request $request)` never touches `$request`. Either use it for the
filters and pagination or drop it.

## Small stuff

- `new Order()` followed by five property assignments is more verbose than `Order::create()`
  once the input is validated.
- The three actions return three different shapes (a bare array, a model, a message object).
  Pick one envelope and stick to it.
- No tests. A feature test for each action would have caught the null in `destroy()` and the
  missing validation in `store()` before review.

## How it could look

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Order::class, 'order');
    }

    public function index(): AnonymousResourceCollection
    {
        return OrderResource::collection(
            Order::with('user')->latest()->paginate(25)
        );
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = Order::create($request->validated() + [
            'user_id' => $request->user()->id,
            'status' => Order::STATUS_PENDING,
        ]);

        return OrderResource::make($order->load('user'))
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Order $order): Response
    {
        $order->delete();

        return response()->noContent();
    }
}
```

Happy to pair on the policy and the audit log if either is unfamiliar. The validation and the
status change are the two I would want in before this merges.
