<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\Request;

class X_CustomerController extends Controller
{
    /**
     * Menampilkan semua data customer. (GET)
     */
    public function index()
    {
        return response()->json(Customer::orderBy('nama_pt')->get());
    }

    /**
     * Menyimpan customer baru. (POST)
     */
    public function store(StoreCustomerRequest $request)
    {
        $customer = Customer::create($request->validated());
        return response()->json($customer, 201); // 201 Created
    }

    /**
     * Menampilkan satu data customer spesifik. (GET by ID)
     */
    public function show(Customer $customer)
    {
        return response()->json($customer);
    }

    /**
     * Memperbarui data customer. (PUT/PATCH)
     */
    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());
        return response()->json($customer);
    }

    /**
     * Menghapus data customer. (DELETE)
     */
    public function destroy(Customer $customer)
    {
        $customer->delete();
        return response()->json(null, 204); // 204 No Content
    }
}