@extends('layouts.app')
@section('title')
    {{__('messages.setting.credentials') }}
@endsection
@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-column">
            <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade show active" id="aoverview" role="tabpanel">
                    @include('user_settings.setting_menu')
                    <div class="card mb-5 mb-xl-10">
                        <div class="collapse show">
                            <div class="card-body p-12">
                                {{ Form::open(['route' => 'user.setting.credential.update', 'id'=>'UserCredentialsSettings', 'class'=>'form']) }}
                                <div class="row">
                                    {{-- STRIPE --}}
                                    {{ Form::hidden('sectionName','user_'.$sectionName) }}
                                    <div class="row mb-3">
                                        <div class="col-lg-1 col-form-label fw-bold fs-6">
                                            {{ Form::label('stripe_enable', __('messages.setting.stripe'), ['class' => 'form-label ']) }}
                                        </div>
                                        <div class="col-lg-8 mt-3">
                                            <div class="form-check form-switch col-6">
                                                <div class="fv-row d-flex align-items-center">
                                                    {{ Form::checkbox('stripe_enable', 1, $setting['stripe_enable'] ?? 0,['class' => 'form-check-input  me-5', 'id' => 'userStripeCheckboxBtn']) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-none user_stripe_div row">
                                        <div class="form-group col-sm-6 mb-5">
                                            {{ Form::label('stripe_key', __('messages.setting.stripe_key').':', ['class' => 'form-label required ']) }}
                                            {{ Form::text('stripe_key', $setting['stripe_key'] ?? null, ['class' => 'form-control'  , 'placeholder' => __('messages.setting.stripe_key'), 'id' => 'UserStripeKey' ]) }}
                                        </div>
                                        <div class="form-group col-sm-6 mb-5">
                                            {{ Form::label('stripe_secret', __('messages.setting.stripe_secret').':', ['class' => 'form-label required ']) }}
                                            {{ Form::text('stripe_secret', $setting['stripe_secret'] ?? null, ['class' => 'form-control',  'placeholder' => __('messages.setting.stripe_secret') , 'id' =>'UserStripeSecret']) }}
                                        </div>
                                    </div>
                                    {{-- PAYPAL --}}
                                    <div class="row mb-3">
                                        <div class="col-lg-1 col-form-label fw-bold fs-6">
                                            {{ Form::label('paypal_enable', __('messages.setting.paypal'), ['class' => 'form-label ']) }}
                                        </div>
                                        <div class="col-lg-8 mt-3">
                                            <div class="form-check form-switch form-switch-sm col-6">
                                                <div class="fv-row d-flex align-items-center">
                                                    {{ Form::checkbox('paypal_enable', 1, $setting['paypal_enable'] ?? 0,['class' => 'form-check-input  me-5', 'id' => 'userPaypalCheckboxBtn']) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-none user_paypal_div row">
                                        <div class="form-group col-sm-6 mb-5">
                                            {{ Form::label('paypal_client_id', __('messages.setting.paypal_client_id').':', ['class' => 'form-label required ']) }}
                                            {{ Form::text('paypal_client_id', $setting['paypal_client_id'] ?? null, ['class' => 'form-control',  'placeholder' => __('messages.setting.paypal_client_id'), 'id' =>'UserPaypalClientId']) }}
                                        </div>
                                        <div class="form-group col-sm-6 mb-5">
                                            {{ Form::label('paypal_secret', __('messages.setting.paypal_secret').':', ['class' => 'form-label required ']) }}
                                            {{ Form::text('paypal_secret', $setting['paypal_secret'] ?? null, ['class' => 'form-control', 'placeholder' => __('messages.setting.paypal_secret'), 'id' => 'userPaypalSecret']) }}
                                        </div>
                                        <div class="form-group col-sm-6 mb-5">
                                            {{ Form::label('paypal_mode', __('messages.setting.paypal_mode').':', ['class' => 'form-label required ']) }}
                                            {{ Form::text('paypal_mode', $setting['paypal_mode'] ?? null, ['class' => 'form-control',  'placeholder' => __('messages.setting.paypal_mode') , 'id' => 'UserPaypalMode']) }}
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer d-flex pt-6 p-0">
                                    <button type="submit"
                                            class="btn btn-primary" id="credentialSaveBtn">{{ __('messages.common.save') }}</button>
                                </div>
                                {{ Form::close() }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
