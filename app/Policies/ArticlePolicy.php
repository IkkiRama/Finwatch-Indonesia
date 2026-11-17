<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\User;

class ArticlePolicy
{
    public function create(User $user): bool
    {
        return $user->hasPermission('articles.create');
    }

    public function update(User $user, Article $article): bool
    {
        if (in_array($article->status, ['published', 'scheduled'], true)) {
            return $user->hasPermission('articles.publish');
        }

        return $user->hasPermission('articles.review') || ($user->hasPermission('articles.edit-own') && $article->author->user_id === $user->id && in_array($article->status, ['draft', 'in_review'], true));
    }

    public function publish(User $user, Article $article): bool
    {
        return $user->hasPermission('articles.publish');
    }

    public function view(User $user, Article $article): bool
    {
        return $this->update($user, $article) || $this->publish($user, $article);
    }

    public function delete(User $user, Article $article): bool
    {
        return $user->hasPermission('articles.publish') || ($article->status === 'draft' && $user->hasPermission('articles.edit-own') && $article->author->user_id === $user->id);
    }
}
