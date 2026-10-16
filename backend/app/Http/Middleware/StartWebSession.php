<?php

namespace App\Http\Middleware;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Session\SessionManager;

class StartWebSession extends StartSession
{
    public function __construct(SessionManager $manager, CacheFactory $cache)
    {
        parent::__construct($manager, fn () => $cache);
    }

    protected function storeCurrentUrl(Request $request, $session)
    {
        // Images and downloads are not navigation destinations. Keeping the page
        // URL lets back() and validation work when the Referer header is absent.
        if ($request->routeIs('media.*', 'letters.download')) {
            return;
        }

        parent::storeCurrentUrl($request, $session);
    }
}
