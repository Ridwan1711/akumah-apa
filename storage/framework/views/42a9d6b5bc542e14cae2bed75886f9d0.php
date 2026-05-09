# Octane

- Octane boots the application once and reuses it across requests, so singletons persist between requests.
- The Laravel container's ___SINGLE_BACKTICK___scoped___SINGLE_BACKTICK___ method may be used as a safe alternative to ___SINGLE_BACKTICK___singleton___SINGLE_BACKTICK___.
- Never inject the container, request, or config repository into a singleton's constructor; use a resolver closure or ___SINGLE_BACKTICK___bind()___SINGLE_BACKTICK___ instead:

___SINGLE_BACKTICK______SINGLE_BACKTICK______SINGLE_BACKTICK___php
// Bad
$this->app->singleton(Service::class, fn (Application $app) => new Service($app['request']));

// Good
$this->app->singleton(Service::class, fn () => new Service(fn () => request()));
___SINGLE_BACKTICK______SINGLE_BACKTICK______SINGLE_BACKTICK___

- Never append to static properties, as they accumulate in memory across requests.
<?php /**PATH C:\Users\Server\Project gueh\siakad - backup\backend\storage\framework\views/ef243e44b2bc7085022d83cfce753c1e.blade.php ENDPATH**/ ?>