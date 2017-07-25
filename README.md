XcoreCMS/InlineEditingNette
===========================

[![Build Status](https://travis-ci.org/XcoreCMS/InlineEditingNette.svg?branch=master)](https://travis-ci.org/XcoreCMS/InlineEditingNette)
[![Coverage Status](https://coveralls.io/repos/github/XcoreCMS/InlineEditingNette/badge.svg?branch=master)](https://coveralls.io/github/XcoreCMS/InlineEditingNette?branch=master)

Inline Editing Nette = Content editable Extension for Nette Framework...


Requirements
------------

XcoreCMS/InlineNette requires:
 
- PHP 7.1+
- Nette 2.4+
- FreezyBee/PrependRoute
- XcoreCMS/InlineEditing


Installation
------------

The best way to install XcoreCMS/InlineEditingNette is using [Composer](http://getcomposer.org/):

```sh
composer require xcore/inline-editing-nette
```

```yaml
extension:
    prependRoute: FreezyBee\PrependRoute\DI\PrependRouteExtension
    inline: XcoreCMS\InlineEditingNette\DI\InlineEditingExtension

inline:
    fallback: en      # default false - fallback locale
    tableName: inline # default inline_content - table name
    persistenceLayer: @doctrine.default.connection # default autodetect (order: doctrine, ndb, dibi)
    url: '/inline-gw' # default '/inline-editing' - route path mask for communication with backend
    allowedRoles: ['admin', 'editor'] # default null
    install:
        assets: false # default true - install assets to www dir
        database: false # default true - create database table
    entityMode: true # default false - turn on entity mode editation
```
