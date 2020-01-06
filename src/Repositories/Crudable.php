<?php

namespace Binaryk\LaravelRestify\Repositories;

use Binaryk\LaravelRestify\Controllers\RestResponse;
use Binaryk\LaravelRestify\Http\Requests\RestifyRequest;
use Binaryk\LaravelRestify\Restify;
use Binaryk\LaravelRestify\Services\Search\SearchService;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @author Eduard Lupacescu <eduard.lupacescu@binarcode.com>
 */
trait Crudable
{
    /**
     * @param  RestifyRequest  $request
     * @param  Paginator  $paginated
     * @return JsonResponse
     */
    public function index(RestifyRequest $request, Paginator $paginated)
    {
        return (new static($paginated))->response();
    }

    /**
     * @param  RestifyRequest  $request
     * @return JsonResponse
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Throwable
     */
    public function show(RestifyRequest $request)
    {
        $repository = $request->newRepositoryWith(tap(SearchService::instance()->prepareRelations($request, $request->findModelQuery()), function ($query) use ($request) {
            $request->newRepository()->detailQuery($request, $query);
        })->firstOrFail());

        $repository->authorizeToView($request);

        return $repository->response();
    }

    /**
     * @param  RestifyRequest  $request
     * @return JsonResponse
     */
    public function store(RestifyRequest $request)
    {
        $model = DB::transaction(function () use ($request) {
            $model = self::fillWhenStore(
                $request, self::newModel()
            );

            $model->save();

            return $model;
        });

        return (new static ($model))
            ->response()
            ->setStatusCode(RestResponse::REST_RESPONSE_CREATED_CODE)
            ->header('Location', Restify::path().'/'.static::uriKey().'/'.$model->id);
    }

    /**
     * @param  RestifyRequest  $request
     * @param $model
     * @return JsonResponse
     */
    public function update(RestifyRequest $request, $model)
    {
        DB::transaction(function () use ($request, $model) {
            $model = static::fillWhenUpdate($request, $model);

            $model->save();

            return $this;
        });

        return $this->response()->setStatusCode(RestResponse::REST_RESPONSE_UPDATED_CODE);
    }

    /**
     * @param  RestifyRequest  $request
     * @return JsonResponse
     */
    public function destroy(RestifyRequest $request)
    {
        DB::transaction(function () use ($request) {
            $model = $request->findModelQuery();

            return $model->delete();
        });

        return $this->response()
            ->setStatusCode(RestResponse::REST_RESPONSE_DELETED_CODE);
    }

    /**
     * @param  null  $request
     * @return mixed
     */
    abstract public function response($request = null);
}