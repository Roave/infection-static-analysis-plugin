# Infection Static Analysis Plugin

This plugin is designed to run static analysis on top of [`infection/infection`](https://github.com/infection/infection)
test runs in order to discover if [escaped mutants](https://en.wikipedia.org/wiki/Mutation_testing)
are valid mutations, or if they do not respect the type signature of your
program. If the mutation would result in a type error, it is "killed".

TL;DR:
 - This will improve your mutation score, since mutations which result in
   type errors become killed.
 - This is very hacky, and replaces `vendor/bin/infection` essentially.
   Please read the `Stability` section below first for details.
 - This is currently much slower than running infection by itself.
   There are ideas/suggestions to improve this in the future.
 
## Usage

The current design of this tool requires you to run `vendor/bin/roave-infection-static-analysis-plugin`
instead of running `vendor/bin/infection`:

```sh
composer require --dev roave/infection-static-analysis-plugin

vendor/bin/roave-infection-static-analysis-plugin
```

### Configuration

The `roave-infection-static-analysis-plugin` binary accepts all of `infection` flags and arguments, and an additional `--psalm-config` argument.

Using `--psalm-config`, you can specify the psalm configuration file to use when analysing the generated mutations:

```sh
vendor/bin/roave-infection-static-analysis-plugin --psalm-config config/psalm.xml
```

## Background

If you come from a statically typed language with AoT compilers, you may be
confused about the scope of this project, but in the PHP ecosystem, producing
runnable code that does not respect the type system is very easy, and mutation
testing tools do this all the time.

Take for example following snippet:

```php
/**
 * @template T
 * @param array<T> $values
 * @return list<T>
 */
function makeAList(array $values): array
{
    return array_values($values);
}
```

Given a valid test as follows:

```php
function test_makes_a_list(): void
{
    $list = makeAList(['a' => 'b', 'c' => 'd']);
 
    assert(count($list) === 2);
    assert(in_array('b', $list, true));
    assert(in_array('d', $list, true));
}
```

The mutation testing framework will produce following mutation, since we
failed to verify the output in a more precise way:

```diff
/**
 * @template T
 * @param array<T> $values
 * @return list<T>
 */
function makeAList(array $values): array
{
-    return array_values($values);
+    return $values;
}
```

The code above is valid PHP, but not valid according to our type declarations.
While we can indeed write a test for this, such test would probably be
unnecessary, as existing type checkers can detect that our actual return value is
no longer a `list<T>`, but a map of `array<int|string, T>`, which is in conflict
with what we declared.

This plugin detects such mutations, and prevents them from making you write
unnecessary tests, leveraging the full power of existing PHP type checkers
such as [phpstan](https://github.com/phpstan/phpstan) and [psalm](https://github.com/vimeo/psalm).

## Stability

Since [`infection/infection`](https://github.com/infection/infection) is not yet
designed to support plugins, this tool uses a very aggressive approach to bootstrap
itself, and relies on internal details of the underlying runner.

To prevent compatibility issues, it therefore always pins to a very specific version
of `infection/infection`, so please be patient when you wish to use the latest and
greatest version of `infection/infection`, as we may still be catching up to it.

Eventually, we will contribute patches to `infection/infection` so that there is a
proper way to design and use plugins, without the need for dirty hacks.

## PHPStan? Psalm? Where's my favourite static analysis tool?

Our initial scope of work for `1.0.x` is to provide `vimeo/psalm` support as a start,
while other static analysers will be included at a later point in time.
