<?php

namespace markhuot\CraftQL\Types;

use yii\base\Component;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use Craft;
use markhuot\CraftQL\Builders\Schema;

class Query extends ObjectType {

    function __construct($request) {
        $token = $request->token();

        $config = [
            'name' => 'Query',
            'fields' => [
                'helloWorld' => [
                    'type' => Type::string(),
                    'resolve' => function ($root, $args) {
                      return 'Welcome to GraphQL! You now have a fully functional GraphQL endpoint.';
                    }
                ],
            ],
        ];

        $schema = new Schema($request);

        if ($token->can('query:entries') && $token->allowsMatch('/^query:entryType/')) {
            if (!empty($request->entryTypes()->all())) {
                $this->addEntriesSchema($schema);
            }
        }

        // var_dump($request->globals()->all());
        // die;

        // foreach ($request->globals()->all() as $globalType) {
            // var_dump($globalType);
            // die;
            $schema->addRawField('globals')
                ->type(\markhuot\CraftQL\Types\GlobalsSet::singleton($request))
                ->resolve(function ($root, $args) {
                    $sets = [];
                    foreach (\Craft::$app->globals->allSets as $set) {
                        $sets[$set->handle] = $set;
                    }
                    return $sets;
                });
        // }

        // foreach ($request->globals()->all() as $set) {
        //     var_dump($set);
        //     die;
        // }

        if ($token->can('query:tags')) {
            $this->addTagsSchema($schema);
        }

        if ($token->can('query:categories')) {
            $config['fields']['categories'] = (new \markhuot\CraftQL\GraphQLFields\Query\Categories($request))->toArray();
            $config['fields']['categoriesConnection'] = (new \markhuot\CraftQL\GraphQLFields\Query\CategoriesConnection($request))->toArray();
        }

        if ($token->can('query:users')) {
            // $config['fields']['users'] = (new \markhuot\CraftQL\GraphQLFields\Query\Users($request))->toArray();
            $schema->addRawField('users')
                ->lists()
                ->type(User::type($request))
                ->arguments([
                    'admin' => Type::boolean(),
                    'email' => Type::string(),
                    'firstName' => Type::string(),
                    'group' => Type::string(),
                    'groupId' => Type::string(),
                    'id' => Type::int(),
                    'lastLoginDate' => Type::int(),
                    'lastName' => Type::string(),
                    'limit' => Type::int(),
                    'offset' => Type::int(),
                    'order' => Type::string(),
                    'search' => Type::string(),
                    // 'status' => static::statusEnum(),
                    'username' => Type::string(),
                ])
                ->resolve(function ($root, $args, $context, $info) {
                    $criteria = \craft\elements\User::find();

                    foreach ($args as $key => $value) {
                        $criteria = $criteria->{$key}($value);
                    }

                    return $criteria->all();
                });
        }

        if ($token->can('query:sections')) {
            $schema->addRawField('sections')->lists()->type(Section::type())->resolve(function ($root, $args, $context, $info) {
                return \Craft::$app->sections->allSections;
            });
        }

        $config['fields'] = array_merge($config['fields'], $schema->config());

        parent::__construct($config);
    }

    /**
     * The fields you can query that return entries
     *
     * @return Schema
     */
    function addEntriesSchema($schema) {
        $schema->addRawField('entries')
            ->lists()
            ->type(Entry::interface($schema->getRequest()))
            ->arguments(Entry::args($schema->getRequest()))
            ->resolve(function ($root, $args, $context, $info) use ($schema) {
                return $schema->getRequest()->entries(\craft\elements\Entry::find(), $root, $args, $context, $info);
            });

        $schema->addRawField('entriesConnection')
            ->name('entriesConnection')
            ->type(EntryConnection::singleton($schema->getRequest()))
            ->arguments(Entry::args($schema->getRequest()))
            ->resolve(function ($root, $args, $context, $info) use ($schema) {
                $criteria = $schema->getRequest()->entries(\craft\elements\Entry::find(), $root, $args, $context, $info);
                list($pageInfo, $entries) = \craft\helpers\Template::paginateCriteria($criteria);

                return [
                    'totalCount' => $pageInfo->total,
                    'pageInfo' => $pageInfo,
                    'edges' => $entries,
                    'criteria' => $criteria,
                    'args' => $args,
                ];
            });

        $schema->addRawField('entry')
            ->type(Entry::interface($schema->getRequest()))
            ->arguments(Entry::args($schema->getRequest()))
            ->resolve(function ($root, $args, $context, $info) use ($schema) {
                return $schema->getRequest()->entries(\craft\elements\Entry::find(), $root, $args, $context, $info)->one();
            });

        $schema->addRawField('drafts')
            ->lists()
            ->type(EntryDraft::interface($schema->getRequest()))
            ->arguments([
                'id' => [
                    'type' => Type::nonNull(Type::int()),
                    'description' => 'The entry id to query for drafts'
                ],
            ])
            ->resolve(function ($root, $args) {
                return \Craft::$app->entryRevisions->getDraftsByEntryId($args['id']);
            });
    }

    /**
     * The fields you can query that return tags
     *
     * @return Schema
     */
    function addTagsSchema($schema) {
        $schema->addRawField('tags')
            ->lists()
            ->type(Tag::interface($schema->getRequest()))
            ->arguments(Tag::args($schema->getRequest()))
            ->resolve(function ($root, $args, $context, $info) {
                $criteria = \craft\elements\Tag::find();

                if (isset($args['group'])) {
                    $args['groupId'] = $args['group'];
                    unset($args['group']);
                }

                foreach ($args as $key => $value) {
                    $criteria = $criteria->{$key}($value);
                }

                return $criteria->all();
            });

        $schema->addRawField('tagsConnection')
            ->type(TagConnection::singleton($schema->getRequest()))
            ->arguments(Tag::args($schema->getRequest()))
            ->resolve(function ($root, $args, $context, $info) {
                $criteria = \craft\elements\Tag::find();

                if (isset($args['group'])) {
                    $args['groupId'] = $args['group'];
                    unset($args['group']);
                }

                foreach ($args as $key => $value) {
                    $criteria = $criteria->{$key}($value);
                }

                list($pageInfo, $tags) = \craft\helpers\Template::paginateCriteria($criteria);

                return [
                    'totalCount' => $pageInfo->total,
                    'pageInfo' => $pageInfo,
                    'edges' => $tags,
                    'criteria' => $criteria,
                    'args' => $args,
                ];
            });
    }

}