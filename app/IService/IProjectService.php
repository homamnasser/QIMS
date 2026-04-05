<?php

namespace App\IService;

use App\Models\Project;

interface IProjectService
{

    public function createProject(array $data): Project;
public function updateProject(Project $project, array $data): Project;}
