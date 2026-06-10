<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class CustomerUpdateRequest extends CustomerStoreRequest
{
    /**
     * On update, ignore the current client's own GSTIN for the unique check.
     */
    protected function gstinUniqueRule(): Rule|string
    {
        return Rule::unique('customers', 'gstin')->ignore($this->route('client'));
    }
}
