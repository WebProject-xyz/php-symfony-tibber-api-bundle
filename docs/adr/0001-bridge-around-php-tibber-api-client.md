# 1. Bridge around php-tibber-api-client

This bundle acts purely as a Symfony 8.1+ integration bridge for `webproject-xyz/php-tibber-api-client` rather than reimplementing GraphQL queries, DTOs, and serializer configurations. We decided to delegate all API modeling and protocol logic to the standalone client library and focus the bundle strictly on Symfony dependency injection, multi-account registration via CompilerPass, CLI integration, and transparent caching decoration.
