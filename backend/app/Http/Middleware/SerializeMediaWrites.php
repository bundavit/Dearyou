<?php

namespace App\Http\Middleware;

use App\Models\Letter;
use App\Models\LetterMemory;
use App\Support\MediaFiles;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class SerializeMediaWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->path();
        if ($request->isMethodSafe() || ! $request->user()
            || ! preg_match('~^(?:(?:admin/|api/)?letters(?:/|$)|(?:admin/)?memories(?:/|$)|(?:admin/)?memory-images(?:/|$)|(?:admin/)?account/profile$)~', $path)) {
            return $next($request);
        }

        return app(MediaFiles::class)->track(fn () => DB::transaction(function () use ($request, $next) {
            // A write acquires the owner row lock on MySQL/Postgres and the
            // SQLite writer lock before quota reads, file writes or persistence.
            $request->user()->newQuery()->whereKey($request->user()->id)
                ->update(['id' => DB::raw('id')]);
            $owner = $request->user()->fresh();
            abort_unless($owner && ! $owner->disabled_at && ! $owner->trashed()
                && (int) $owner->session_version === (int) $request->user()->session_version, 403);
            $request->user()->setRawAttributes($owner->getAttributes(), true);

            foreach ($request->route()->parameters() as $parameter) {
                if ($parameter instanceof Letter || $parameter instanceof LetterMemory) {
                    $parameter->refresh();
                    abort_if(method_exists($parameter, 'trashed') && $parameter->trashed(), 404);
                }
            }

            $response = $next($request);
            // The routing pipeline may render exceptions before returning here.
            // Re-throw so both database writes and new files are rolled back.
            if (($response->exception ?? null) instanceof \Throwable) {
                throw $response->exception;
            }
            if ($response->getStatusCode() >= 400) {
                throw new HttpResponseException($response);
            }

            return $response;
        }));
    }
}
