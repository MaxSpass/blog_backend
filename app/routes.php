<?php
declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

return function (App $app) {


    /*Posts*/
    $app->get('/posts', function (Request $request, Response $response) {
        $db = $this->get(PDO::class);
        $sth = $db->prepare("SELECT * FROM posts");
        $sth->execute();
        $posts = $sth->fetchAll(PDO::FETCH_ASSOC);

        foreach ($posts as &$post) {
            $sth2 = $db->prepare("SELECT tag_id FROM posts_has_tags WHERE post_id = :post_id");
            $sth2->execute([
                'post_id' => (int) $post['id']
            ]);

            $sth3 = $db->prepare("SELECT category_id FROM posts_has_categories WHERE post_id = :post_id");
            $sth3->execute([
                'post_id' => (int) $post['id']
            ]);

            $post["tags"] = $sth2->fetchAll(PDO::FETCH_COLUMN, 0); /*@TODO Should Change*/
            $post["categories"] = $sth3->fetchAll(PDO::FETCH_COLUMN, 0); /*@TODO Should Change*/
        }

        $response->getBody()->write((string) json_encode([
            'success' => true,
            'data' => $posts
        ]));
        $response = $response->withHeader('Content-Type', 'application/json');
        return $response;
    });

    $app->post('/posts', function (Request $request, Response $response) {
        $db = $this->get(PDO::class);
        $body = $request->getParsedBody();
        $errors = [];

        if (!isset($body['title']) || empty($body['title'])) {
            $errors[] = 'Title is required';
        }

        if (!isset($body['content']) || empty($body['content'])) {
            $errors[] = 'Content is required';
        }

        if (!isset($body['user']) || empty($body['user'])) {
            $errors[] = 'User is required';
        } else {
            $sth = $db->prepare("SELECT id FROM users WHERE id = :user LIMIT 1");
            $sth->execute([
                'user' => (int) $body['user']
            ]);

            $user = $sth->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $errors[] = 'User with id ' . $body['user'] . ' doesn\'t exist';
            }
        }


        if (isset($body['categories']) && !empty($body['categories']) && is_array($body['categories'])) {
            foreach ($body['categories'] as $categoryId) {
                $sth = $db->prepare("SELECT id FROM categories WHERE id = :category LIMIT 1");
                $sth->execute([
                    'category' => (int) $categoryId
                ]);

                $category = $sth->fetch(PDO::FETCH_ASSOC);

                if (!$category) {
                    $errors[] = 'Category with id ' . $categoryId . ' doesn\'t exist';
                }
            }
        }

        if (isset($body['tags']) && !empty($body['tags']) && is_array($body['tags'])) {
            foreach ($body['tags'] as $tagId) {
                $sth = $db->prepare("SELECT id FROM tags WHERE id = :tag LIMIT 1");
                $sth->execute([
                    'tag' => (int) $tagId
                ]);

                $tag = $sth->fetch(PDO::FETCH_ASSOC);

                if (!$tag) {
                    $errors[] = 'Tag with id ' . $tagId . ' doesn\'t exist';
                }
            }
        }

        if (empty($errors)) {
            $sth = $db->prepare("INSERT INTO posts (title, content, user_id, thumbnail) VALUES (:title, :content, :user, :thumbnail)");
            $sth->execute([
                'title' => $body['title'],
                'content' => $body['content'],
                'user' => (int) $body['user'],
                'thumbnail' => $body['thumbnail']
            ]);

            $postId = (int) $db->lastInsertId();

            if ($postId) {
                if (isset($body['categories']) && !empty($body['categories'])) {
                    foreach ($body['categories'] as $categoryId) {
                        $sth = $db->prepare("INSERT INTO posts_has_categories (post_id, category_id) VALUES (:post_id, :category_id)");
                        $sth->execute([
                            'post_id' => $postId,
                            'category_id' => (int) $categoryId
                        ]);
                    }
                }

                if (isset($body['tags']) && !empty($body['tags'])) {
                    foreach ($body['tags'] as $tagId) {
                        $sth = $db->prepare("INSERT INTO posts_has_tags (post_id, tag_id) VALUES (:post_id, :tag_id)");
                        $sth->execute([
                            'post_id' => $postId,
                            'tag_id' => (int) $tagId
                        ]);
                    }
                }

                $sth = $db->prepare("SELECT * FROM posts WHERE id = :post LIMIT 1");
                $sth->execute([
                    'post' => (int) $postId
                ]);

                $post = $sth->fetch(PDO::FETCH_ASSOC);

                $responseData = [
                    'success' => true,
                    'data' => $post
                ];

            } else {
                $responseData = [
                    'success' => false,
                    'errors' => ['Post cant insert']
                ];
            }
        } else {
            $responseData = [
                'success' => false,
                'errors' => $errors
            ];
        }


        $response->getBody()->write((string) json_encode($responseData));
        $response = $response->withHeader('Content-Type', 'application/json');
        return $response;
    });

    /*Update post*/
    $app->post('/posts/{id}', function (Request $request, Response $response) {
        $db = $this->get(PDO::class);
        $body = $request->getParsedBody();
        $errors = [];
        $postId = (int) $request->getAttribute('id');

        if (!isset($body['title']) || empty($body['title'])) {
            $errors[] = 'Title is required';
        }

        if (!isset($body['content']) || empty($body['content'])) {
            $errors[] = 'Content is required';
        }

        if (!isset($body['user']) || empty($body['user'])) {
            $errors[] = 'User is required';
        } else {
            $sth = $db->prepare("SELECT id FROM users WHERE id = :user LIMIT 1");
            $sth->execute([
                'user' => (int) $body['user']
            ]);

            $user = $sth->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $errors[] = 'Юзер с ID: ' . $body['user'] . ' не существует';
            }
        }


        if (isset($body['categories']) && !empty($body['categories']) && is_array($body['categories'])) {
            foreach ($body['categories'] as $categoryId) {
                $sth = $db->prepare("SELECT id FROM categories WHERE id = :category LIMIT 1");
                $sth->execute([
                    'category' => (int) $categoryId
                ]);

                $category = $sth->fetch(PDO::FETCH_ASSOC);

                if (!$category) {
                    $errors[] = 'Category with id ' . $categoryId . ' doesn\'t exist';
                }
            }
        }

        if (isset($body['tags']) && !empty($body['tags']) && is_array($body['tags'])) {
            foreach ($body['tags'] as $tagId) {
                $sth = $db->prepare("SELECT id FROM tags WHERE id = :tag LIMIT 1");
                $sth->execute([
                    'tag' => (int) $tagId
                ]);

                $tag = $sth->fetch(PDO::FETCH_ASSOC);

                if (!$tag) {
                    $errors[] = 'Tag with id ' . $tagId . ' doesn\'t exist';
                }
            }
        }

        if (empty($errors)) {
            $sth = $db->prepare("UPDATE posts SET title = :title, content = :content, user_id = :user_id WHERE id = :post_id");
            $sth->execute([
                'title' => $body['title'],
                'content' => $body['content'],
                'user_id' => (int) $body['user'],
                'post_id' => (int) $postId,
            ]);

            if (isset($body['categories']) && !empty($body['categories'])) {
                foreach ($body['categories'] as $categoryId) {
                    $sth = $db->prepare("UPDATE posts_has_categories SET post_id = :post_id, category_id = :category_id WHERE post_id = :post_id");
                    $sth->execute([
                        'post_id' => $postId,
                        'category_id' => (int) $categoryId
                    ]);
                }
            }

            if (isset($body['tags']) && !empty($body['tags'])) {
                foreach ($body['tags'] as $tagId) {
                    $sth = $db->prepare("UPDATE posts_has_tags SET post_id = :post_id, tag_id = :tag_id WHERE post_id = :post_id");
                    $sth->execute([
                        'post_id' => $postId,
                        'tag_id' => (int) $tagId
                    ]);
                }
            }

            $sth = $db->prepare("SELECT * FROM posts WHERE id = :id LIMIT 1");
            $sth->execute([
                'id' => (int) $postId
            ]);

            $post = $sth->fetch(PDO::FETCH_ASSOC);

            $responseData = [
                'success' => true,
                'data' => $post
            ];

        } else {
            $responseData = [
                'success' => false,
                'errors' => $errors
            ];
        }


        $response->getBody()->write((string) json_encode($responseData));

        return $response;
    });

    $app->delete('/posts/{id}', function (Request $request, Response $response) {
        $db = $this->get(PDO::class);
        $postId = (int) $request->getAttribute('id');

        $sth = $db->prepare("SELECT * FROM posts WHERE id = :id LIMIT 1");
        $sth->execute([
            'id' => (int) $postId
        ]);

        $post = $sth->fetch(PDO::FETCH_ASSOC);

        if($post) {
            $sth2 = $db->prepare("DELETE FROM posts WHERE id = :id");
            $sth2->execute([
                'id' => (int) $postId
            ]);

            $responseData = [
                'success' => true,
                'message' => ['Пост был удалён'],
            ];
        } else {
            $responseData = [
                'success' => false,
                'errors' => ['Не существует такой записи']
            ];
        }
        $response->getBody()->write((string) json_encode($responseData));

        return $response;
    });

    /*Roles*/
    $app->get('/roles', function (Request $request, Response $response) {
        $db = $this->get(PDO::class);
        $sth = $db->prepare("SELECT * FROM roles");
        $sth->execute();
        $data = $sth->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write((string) json_encode([
            'success' => true,
            'data' => $data
        ]));
        $response = $response->withHeader('Content-Type', 'application/json');
        return $response;
    });

    $app->get('/auth', function (Request $request, Response $response) {
        $db = $this->get(PDO::class);
        $params = $request->getQueryParams();
        $errors = [];
        $hasId = isset($params["id"]) && !empty($params['id']);
        $hasName = isset($params["name"]) && !empty($params['name']);
        $sql = "SELECT * FROM users WHERE";
        $optionals = [];
        $exec = [];
        $user = [];

        if($hasId || $hasName) {
            if($hasName) {
                $optionals[] = " name = :name ";
                $exec['name'] = $params["name"];
            }

            foreach($optionals as $i=>$item) {
                $sql.=$item;
                if($i+1 < count($optionals)) {
                    $sql.= "AND";
                }
            }

            $sql .= "LIMIT 1";
            $sth = $db->prepare($sql);
            $sth->execute($exec);
            $user = $sth->fetch(PDO::FETCH_ASSOC);
        }

        if(!$hasId && !$hasName || !$user){
            $errors[] = "Нет такого пользователя";
        }

        if (empty($errors)) {
            $responseData = [
                'success' => true,
                'data' => $user
            ];
        } else {
            $responseData = [
                'success' => false,
                'errors' => $errors
            ];
        }

        $response->getBody()->write((string) json_encode($responseData));

        return $response;
    });

    /*Users*/
    $app->get('/users', function (Request $request, Response $response) {
        $db = $this->get(PDO::class);
        $sth = $db->prepare("SELECT * FROM users");
        $sth->execute();
        $data = $sth->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write((string) json_encode([
            'success' => true,
            'data' => $data
        ]));
        $response = $response->withHeader('Content-Type', 'application/json');
        return $response;
    });

    $app->get('/users/{id}', function (Request $request, Response $response) {
        $db = $this->get(PDO::class);
        $userId = (int) $request->getAttribute('id');

        $sth = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $sth->execute([
            'id' => (int) $userId
        ]);

        $user = $sth->fetch(PDO::FETCH_ASSOC);

        if($user) {
            $responseData = [
                'success' => true,
                'data' => $user
            ];
        } else {
            $responseData = [
                'success' => false,
                'errors' => ['There is no user with provided ID: ']
            ];
        }
        $response->getBody()->write((string) json_encode($responseData));

        return $response;
    });

    $app->post('/users', function (Request $request, Response $response) {
        $db = $this->get(PDO::class);
        $body = $request->getParsedBody();
        $errors = [];

        if (!isset($body['name']) || empty($body['name'])) {
            $errors[] = 'Name is required';
        }

        if (!isset($body['role_id']) || empty($body['role_id'])) {
            $errors[] = 'Role ID is required';
        }

        /*Is the same name*/
        $sth = $db->prepare("SELECT name FROM users WHERE name = :name LIMIT 1");
        $sth->execute([
            'name' => $body['name']
        ]);

        $user = $sth->fetch(PDO::FETCH_ASSOC);

        if($user) {
            $errors[] = 'Это имя уже занято';
        }

        if (empty($errors) && !$user) {
            $sth = $db->prepare("INSERT INTO users (name, role_id) VALUES (:name, :role_id)");
            $sth->execute([
                'name' => $body['name'],
                'role_id' => $body['role_id'],
            ]);

            $userId = (int) $db->lastInsertId();

            $sth = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
            $sth->execute([
                'id' => (int) $userId
            ]);

            $user = $sth->fetch(PDO::FETCH_ASSOC);

            $responseData = [
                'success' => true,
                'data' => $user
            ];

        } else {
            $responseData = [
                'success' => false,
                'errors' => $errors
            ];
        }

        $response->getBody()->write((string) json_encode($responseData));

        return $response;
    });

    /*Tags*/
    $app->get('/tags/{id}', function (Request $request, Response $response) {
        $db = $this->get(PDO::class);
        $tagId = (int) $request->getAttribute('id');

        $sth = $db->prepare("SELECT * FROM tags WHERE id = :id LIMIT 1");
        $sth->execute([
            'id' => (int) $tagId
        ]);

        $tag = $sth->fetch(PDO::FETCH_ASSOC);

        if($tag) {
            $responseData = [
                'success' => true,
                'data' => $tag
            ];
        } else {
            $responseData = [
                'success' => false,
                'errors' => ['There is no tag with provided ID: '.$tagId]
            ];
        }
        $response->getBody()->write((string) json_encode($responseData));

        return $response;
    });

    /*Categories*/
    $app->get('/categories/{id}', function (Request $request, Response $response) {
        $db = $this->get(PDO::class);
        $catId = (int) $request->getAttribute('id');

        $sth = $db->prepare("SELECT * FROM categories WHERE id = :id LIMIT 1");
        $sth->execute([
            'id' => (int) $catId
        ]);

        $cat = $sth->fetch(PDO::FETCH_ASSOC);

        if($cat) {
            $responseData = [
                'success' => true,
                'data' => $cat
            ];
        } else {
            $responseData = [
                'success' => false,
                'errors' => ['There is no category with provided ID: '.$catId]
            ];
        }
        $response->getBody( )->write((string) json_encode($responseData));

        return $response;
    });




//    $app->group('/users', function (Group $group) {
//        $group->get('', ListUsersAction::class);
//        $group->get('/{id}', ViewUserAction::class);
//    });

    $app->map(['GET', 'POST', 'PUT', 'DELETE', 'PATCH'], '/{routes:.+}', function ($request, $response) {
        throw new HttpNotFoundException($request);
    });
};
