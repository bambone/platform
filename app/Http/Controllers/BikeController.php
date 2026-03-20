<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBikeRequest;
use App\Http\Requests\UpdateBikeRequest;
use App\Models\Bike;

class BikeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBikeRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Bike $bike)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Bike $bike)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateBikeRequest $request, Bike $bike)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bike $bike)
    {
        //
    }
}
