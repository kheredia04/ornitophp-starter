# Validation

`Ornito\Validation\Validator` is a rule-string validator. Rules are pipe-separated strings (`required|email|min:8`). It returns a `field => first failure message` map — empty when valid, no exceptions on the happy path.

## Basic usage

```php
use Ornito\Validation\Validator;

$errors = Validator::validate(
    $_POST,
    [
        'email' => 'required|email',
        'password' => 'required|min:8',
    ],
);

if ($errors !== []) {
    // Flash errors back to the form
    Session::flash('errors', $errors);
    return Response::redirect('/login');
}
```

## Available rules

| Rule | Parameter | Description | Skips empty? |
|---|---|---|---|
| `required` | — | Field must be present and non-empty | No (this IS the presence gate) |
| `email` | — | Must be a valid email address | Yes |
| `min:n` | positive int | Minimum length (inclusive) | Yes |
| `max:n` | positive int | Maximum length (inclusive) | Yes |
| `alpha` | — | Only letters (Unicode-aware: supports accents) | Yes |
| `alpha_num` | — | Only letters and numbers (Unicode-aware) | Yes |
| `numeric` | — | Only digits (`ctype_digit`) | Yes |
| `in:val1,val2,...` | comma-separated list | Value must be one of the allowed values (case-sensitive) | Yes |
| `url` | — | Must be a valid URL | Yes |
| `date` | — | Must be a valid date (`strtotime`) | Yes |
| `confirmed` | — | Must match `{field}_confirmation` value | Yes |
| `array` | — | Must be a PHP array | No |

### Empty value behavior

An empty string counts as "missing" — `required` fails. All other rules **skip empty values**: only `required` gates presence. This matches Laravel's implicit-rule behavior.

```php
// bio is empty — but it's not required, so all rules pass
$errors = Validator::validate(['bio' => ''], ['bio' => 'email|min:3|max:10']);
// $errors = [] (empty)
```

### The `in` rule

```php
$errors = Validator::validate(
    ['color' => 'purple'],
    ['color' => 'in:red,blue,green'],
);
// $errors = ['color' => 'The color field must be one of: red, blue, green.']
```

The `in` rule is **case-sensitive**: `'Admin'` does NOT match `'admin'`.

### The `confirmed` rule

Useful for password confirmation fields:

```php
$errors = Validator::validate(
    ['password' => 'secret', 'password_confirmation' => 'different'],
    ['password' => 'confirmed'],
);
// $errors = ['password' => 'The password field confirmation does not match.']
```

The `confirmed` rule looks for a field named `{field}_confirmation` in the data array.

## Combining rules

Pipe-separate multiple rules. They are evaluated left to right; the **first** failure per field wins:

```php
$errors = Validator::validate(
    ['email' => 'nope', 'password' => ''],
    [
        'email' => 'required|email',
        'password' => 'required|min:8|confirmed',
    ],
);
// $errors = [
//     'email' => 'The email field must be a valid email address.',
//     'password' => 'The password field is required.',
// ]
```

## Error messages

Every failure produces a message in the format:

```
The {field} field {description}.
```

Messages are in English by default. The `in` rule appends the allowed values:

```
The color field must be one of: red, blue, green.
```

## ValidationException

When a rule string is malformed (unknown rule, missing parameter), the validator throws `Ornito\Validation\ValidationException`:

```php
use Ornito\Validation\ValidationException;

try {
    Validator::validate(['id' => '1'], ['id' => 'unknown_rule']);
} catch (ValidationException $e) {
    // $e->getMessage() => "Unknown validation rule [unknown_rule]."
}
```

This is a hard failure — not a validation error. It means the code has a bug, not the input.

## Full example: controller integration

```php
public function store(Request $request): Response
{
    $errors = Validator::validate($request->body, [
        'name' => 'required|alpha|min:2|max:100',
        'email' => 'required|email',
        'password' => 'required|min:8|confirmed',
        'role' => 'in:admin,user',
    ]);

    if ($errors !== []) {
        Session::flash('errors', $errors);
        Session::flash('old', $request->body);
        return Response::redirect('/register');
    }

    // All valid — create the user
    $id = User::insert([
        'name' => $request->body['name'],
        'email' => $request->body['email'],
        'password_hash' => password_hash($request->body['password'], PASSWORD_DEFAULT),
    ]);

    return Response::redirect("/users/{$id}");
}
```
