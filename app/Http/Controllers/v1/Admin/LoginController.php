<?php

namespace App\Http\Controllers\v1\Admin;

use App\Code;
use App\Models\v1\Admin;
use App\Models\v1\AdminLog;
use App\Models\v1\AuthRule;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    public function index(Request $request)
    {

        $admin = Admin::query()->where('name', $request->username)->first();
        if (!$admin) {
            return resReturn(0, '账号不存在', Code::CODE_INEXISTENCE);
        }
        if (!Hash::check($request->password, $admin->password)) {
            return resReturn(0, '密码错误', Code::CODE_WRONG);
        }
        $admin->last_login_at = Carbon::now()->toDateTimeString();
        $admin->save();
        $access_token = '';

        if ($this->hasTooManyLoginAttempts($request)) {
            $this->limiter()->clear($this->throttleKey($request));
            $this->fireLockoutEvent($request);
            return $this->sendLockoutResponse($request);
        }

        if ($request->type == 1) {  //首次登录获取token
            $client = new Client();//使用client类post参数
            $url = request()->root() . '/oauth/token';
            $params = array_merge(config('passport.admin.proxy'), [
                'username' => $request->username,
                'password' => $request->password,
            ]);
            $respond = $client->post($url, ['form_params' => $params]);
            $access_token = json_decode($respond->getBody()->getContents(), true);
            //json_decode对json格式的代码进行编码
        } else if ($request->type == 2) {    //token失效更新token
            $client = new Client();
            $url = request()->root() . '/oauth/token';
            $params = array_merge(config('passport.admin.refresh'), [
                'refresh_token' => $request->refresh_token,
            ]);
            $respond = $client->post($url, ['form_params' => $params]);
            $access_token = json_decode($respond->getBody()->getContents(), true);
        }
        $access_token['refresh_expires_in'] = config('passport.refresh_expires_in') / 60 / 60 / 24;
        $this->incrementLoginAttempts($request);
        //日志记录
        $input = $request->all();
        $log = new AdminLog();
        $log->admin_id = $admin->id;
        $log->path = $request->path();
        $log->method = $request->method();
        $log->ip = $request->ip();
        $log->input = json_encode($input, JSON_UNESCAPED_UNICODE);
        $log->save();   # 记录日志
        return resReturn(1, $access_token);
    }

    /**
     * token刷新
     * @param Request $request
     * @return string
     */
    public function refresh(Request $request)
    {
        $client = new Client();
        $url = request()->root() . '/oauth/token';
        $params = array_merge(config('passport.admin.refresh'), [
            'refresh_token' => $request->refresh_token,
        ]);
        $respond = $client->post($url, ['form_params' => $params]);
        $access_token = json_decode($respond->getBody()->getContents(), true);
        return resReturn(1, $access_token);
    }

    //获取管理员信息
    public function userInfo(Request $request)
    {
        $user = auth('api')->user();
        $data['name'] = $user->name;
        if ($user->portrait) {
            $data['avatar'] = $user->portrait;
        } else {
            $data['avatar'] = request()->root() . '/storage/image/avatar/1.gif';
        }
        $group = auth('api')->user()->authGroup->toArray();
        //权限名只取一个（多个权限名称太长）
        $data['introduction'] = $group[0]['introduction'];
        foreach ($group as $u) {
            $data['roles'][] = $u['roles'];
        }
        //获取该权限组的菜单
        $AuthRule = AuthRule::with(['AuthGroup' => function ($query) {
            $query->select('roles');
        }])->orderBy('pid', 'ASC')->orderBy('sort', 'ASC')->orderBy('id', 'ASC')->get();
        $data['asyncRouterMap'] = [];   //菜单
        $data['jurisdiction'] = []; //权限列表可用于侧边栏递归导航
        $asyncRouterMap = [];
        foreach ($AuthRule as $id => $rule) {
            $rolesArray = [];
            if (count($rule->AuthGroup) > 0) {
                foreach ($rule->AuthGroup as $group) {
                    $rolesArray[] = $group->roles;
                    $data['jurisdiction'][$rule->api][] = $group->roles;
                }

            }
            if ($rule->type == 0) {
                $activeMenu = '';
                if (strpos($rule->api, 'Create') !== false) {
                    $activeMenu = str_replace('Create', '', $rule->api) . 'List';
                } else if (strpos($rule->api, 'Edit') !== false) {
                    $activeMenu = str_replace('Edit', '', $rule->api) . 'List';
                } else if (strpos($rule->api, 'Detail') !== false) {
                    $activeMenu = str_replace('Detail', '', $rule->api) . 'List';
                }
                $asyncRouterMap[] = array(
                    'id' => $rule->id,
                    'pid' => $rule->pid,
                    'path' => $rule->pid > 0 ? lcfirst($rule->api) : '/' . lcfirst($rule->api),
                    'component' => $rule->pid > 0 ? $rule->api : 'Layout',
                    'redirect' => (strpos($rule->api, 'List') !== false || strpos($rule->api, 'Create') !== false || strpos($rule->api, 'Edit') !== false || strpos($rule->api, 'Detail') !== false) ? $rule->url : 'noredirect',
                    'alwaysShow' => $rule->state,
                    'name' => $rule->api,
                    'hidden' => $rule->state == 1 && array_intersect($data['roles'], $rolesArray) ? false : true,
                    'meta' => array(
                        'title' => $rule->title,
                        'icon' => $rule->icon,
                        'roles' => $rolesArray,
                        'noCache' => false,
                        'breadcrumb' => true,
                        'activeMenu' => $activeMenu
                    ),
                );
            }
        }
        $data['asyncRouterMap'] = genTree($asyncRouterMap, 'pid');
        return resReturn(1, $data);
    }
}
