<?php

namespace App\Http\Controllers;

use App\Models\Workflow;
use App\Services\DefinitionValidator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeRequester($request);
        $workflows = Workflow::where('status', 'draft')->whereHas('versions')->with('latestPublishedVersion')->orderBy('name')->get()->map(fn ($workflow) => [...$workflow->only(['id', 'name', 'code', 'description', 'category']), 'version' => $workflow->latestPublishedVersion->version, 'version_id' => $workflow->latestPublishedVersion->id]);

        return Inertia::render('catalog/index', ['workflows' => $workflows]);
    }

    public function show(Request $request, Workflow $workflow, DefinitionValidator $validator)
    {
        $this->authorizeRequester($request);
        abort_if($workflow->status === 'archived', 404);
        $version = $workflow->latestPublishedVersion;
        abort_unless($version, 404);

        return Inertia::render('catalog/show', ['workflow' => $workflow->only(['id', 'name', 'code', 'description', 'category']), 'version' => $version->only(['id', 'version']), 'fields' => $validator->fields($version->definition)]);
    }

    private function authorizeRequester(Request $request): void
    {
        abort_unless($request->user()->can('requests.submit') || $request->user()->hasRole('Super Admin'), 403);
    }
}
