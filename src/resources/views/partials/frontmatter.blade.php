title: API Reference

language_tabs:
- bash
- javascript

includes:
- errors

search: true

parsedRoutes:
 @foreach($parsedRoutes as $group => $routes)
 {{ $group }}:
  -
 @foreach($routes as $parsedRoute)
   {{ $parsedRoute['id'] }}: {{ $parsedRoute['title'] }}
 @endforeach
@endforeach