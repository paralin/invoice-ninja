<?php namespace App\Http\Controllers;
/*
|--------------------------------------------------------------------------
| Confide Controller Template
|--------------------------------------------------------------------------
|
| This is the default Confide controller template for controlling user
| authentication. Feel free to change to your needs.
|
*/

use Auth;
use Session;
use DB;
use Validator;
use Input;
use View;
use Redirect;
use Datatable;
use URL;

use App\Models\AccountToken;

use App\Ninja\Repositories\AccountRepository;

class TokenController extends BaseController
{
    public function index()
    {
        return Redirect::to('settings/' . ACCOUNT_API_TOKENS);
    }

    public function getDatatable()
    {
        $query = DB::table('account_tokens')
                  ->where('account_tokens.account_id', '=', Auth::user()->account_id);

        if (!Session::get('show_trash:token')) {
            $query->where('account_tokens.deleted_at', '=', null);
        }

        $query->select('account_tokens.public_id', 'account_tokens.name', 'account_tokens.token', 'account_tokens.public_id', 'account_tokens.deleted_at');

        return Datatable::query($query)
        ->addColumn('name', function ($model) { return link_to('tokens/'.$model->public_id.'/edit', $model->name); })
        ->addColumn('token', function ($model) { return $model->token; })
        ->addColumn('dropdown', function ($model) {
          $actions = '<div class="btn-group tr-action" style="visibility:hidden;">
              <button type="button" class="btn btn-xs btn-default dropdown-toggle" data-toggle="dropdown">
                '.trans('texts.select').' <span class="caret"></span>
              </button>
              <ul class="dropdown-menu" role="menu">';

          if (!$model->deleted_at) {
              $actions .= '<li><a href="'.URL::to('tokens/'.$model->public_id).'/edit">'.uctrans('texts.edit_token').'</a></li>
                           <li class="divider"></li>
                           <li><a href="javascript:deleteToken('.$model->public_id.')">'.uctrans('texts.delete_token').'</a></li>';
          }

           $actions .= '</ul>
          </div>';

          return $actions;
        })
        ->orderColumns(['name', 'token'])
        ->make();
    }

    public function edit($publicId)
    {
        $token = AccountToken::where('account_id', '=', Auth::user()->account_id)
                        ->where('public_id', '=', $publicId)->firstOrFail();

        $data = [
            'token' => $token,
            'method' => 'PUT',
            'url' => 'tokens/'.$publicId,
            'title' => trans('texts.edit_token'),
        ];

        return View::make('accounts.token', $data);
    }

    public function update($publicId)
    {
        return $this->save($publicId);
    }

    public function store()
    {
        return $this->save();
    }

    /**
     * Displays the form for account creation
     *
     */
    public function create()
    {
        $data = [
          'token' => null,
          'method' => 'POST',
          'url' => 'tokens',
          'title' => trans('texts.add_token'),
        ];

        return View::make('accounts.token', $data);
    }

    public function delete()
    {
        $tokenPublicId = Input::get('tokenPublicId');
        $token = AccountToken::where('account_id', '=', Auth::user()->account_id)
                    ->where('public_id', '=', $tokenPublicId)->firstOrFail();

        $token->delete();

        Session::flash('message', trans('texts.deleted_token'));

        return Redirect::to('settings/' . ACCOUNT_API_TOKENS);
    }

    /**
     * Stores new account
     *
     */
    public function save($tokenPublicId = false)
    {
        if (Auth::user()->account->isPro()) {
            $rules = [
                'name' => 'required',
            ];

            if ($tokenPublicId) {
                $token = AccountToken::where('account_id', '=', Auth::user()->account_id)
                            ->where('public_id', '=', $tokenPublicId)->firstOrFail();
            }

            $validator = Validator::make(Input::all(), $rules);

            if ($validator->fails()) {
                return Redirect::to($tokenPublicId ? 'tokens/edit' : 'tokens/create')->withInput()->withErrors($validator);
            }

            if ($tokenPublicId) {
                $token->name = trim(Input::get('name'));
            } else {
                $token = AccountToken::createNew();
                $token->name = trim(Input::get('name'));
                $token->token = str_random(RANDOM_KEY_LENGTH);
            }

            $token->save();

            if ($tokenPublicId) {
                $message = trans('texts.updated_token');
            } else {
                $message = trans('texts.created_token');
            }

            Session::flash('message', $message);
        }

        return Redirect::to('settings/' . ACCOUNT_API_TOKENS);
    }

}
