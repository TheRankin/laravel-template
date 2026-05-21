<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Labels\StoreLabelRequest;
use App\Http\Requests\Labels\UpdateLabelRequest;
use App\Http\Resources\LabelResource;
use App\Models\Label;
use App\Models\Project;
use Illuminate\Http\Response;

class LabelController extends Controller
{
    public function index(Project $project)
    {
        $this->authorize('viewAny', [Label::class, $project]);

        $labels = $project->labels()->orderBy('name')->get();

        return LabelResource::collection($labels);
    }

    public function store(StoreLabelRequest $request, Project $project): LabelResource
    {
        $this->authorize('create', [Label::class, $project]);

        $data = $request->validated();
        $data['project_id'] = $project->id;

        $label = Label::create($data);

        return new LabelResource($label);
    }

    public function update(UpdateLabelRequest $request, Label $label): LabelResource
    {
        $this->authorize('update', $label);

        $label->fill($request->validated());
        $label->save();

        return new LabelResource($label);
    }

    public function destroy(Label $label): Response
    {
        $this->authorize('delete', $label);

        $label->delete();

        return response()->noContent();
    }
}
