<?php

namespace ConfrariaWeb\Task\Controllers;

use ConfrariaWeb\Fullcalendar\Calendar;
use ConfrariaWeb\Task\Models\Task;
use ConfrariaWeb\Task\Requests\StoreTaskCommentRequest;
use ConfrariaWeb\Task\Requests\StoreTaskRequest;
use ConfrariaWeb\Task\Requests\UpdateTaskRequest;
use ConfrariaWeb\Task\Resources\TaskResource;
use ConfrariaWeb\Task\Resources\UserResource;
use Auth;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TaskController extends Controller
{

    protected $data;

    public function __construct()
    {

    }

    public function index(Request $request, $page = null)
    {
        $all = $request->all();
        $this->data['get'] = $all;
        $this->data['types'] = resolve('TaskTypeService')->all();
        $this->data['employees'] = resolve('UserService')->employees();
        $this->data['responsibles'] = resolve('UserService')->employees();

        $this->data['datatable'] = [
            'id' => 'datatable_tasks',
            'items' => ['title', 'date', 'time', 'status', 'priority', 'destinateds', 'responsibles'],
            'url' => 'tasks',
            'slug' => 'tasks'
        ];

        if ($page == 'calendar') {
            return view(config('cw_task.views') . 'calendar', $this->data);
        }
        return view(config('cw_task.views') . 'index', $this->data);
    }

    public function calendar(){
        $events = [];

        $events[] = \Calendar::event(
            'Event One', //event title
            false, //full day event?
            '2015-02-11T0800', //start time (you can also use Carbon instead of DateTime)
            '2015-02-12T0800', //end time (you can also use Carbon instead of DateTime)
            0 //optionally, you can specify an event ID
        );

        $events[] = \Calendar::event(
            "Valentine's Day", //event title
            true, //full day event?
            new \DateTime('2015-02-14'), //start time (you can also use Carbon instead of DateTime)
            new \DateTime('2015-02-14'), //end time (you can also use Carbon instead of DateTime)
            'stringEventId' //optionally, you can specify an event ID
        );

        $eloquentEvent = Task::first();

        $calendar = \Calendar::addEvents($events)
        ->addEvent($eloquentEvent, [
            'color' => '#800',
        ])->setOptions([
            'firstDay' => 1
        ])->setCallbacks([
            'viewRender' => 'function() {alert("Callbacks!");}'
        ]);

        return view(config('cw_task.views') . 'calendar', compact('calendar'));

    }

    public function create(Request $request)
    {
        $this->data['statuses'] = resolve('TaskStatusService')->all();
        $taskemployees = ($request->taskemployees) ? $request->taskemployees : [Auth::user()->id];
        $this->data['tasks'] = resolve('TaskService')->take(10)->where(['employees', $taskemployees])->get();
        $this->data['types'] = resolve('TaskTypeService')->all();
        $this->data['selecteds']['destinateds'] = [];
        if (isset($request->destinated_id)) {
            $destinateds = resolve('UserService')->find($request->destinated_id);
            $this->data['selecteds']['destinateds'] = ($destinateds) ? $destinateds->pluck('name', 'id') : [];
        }
        $this->data['selecteds']['responsibles'] = [auth()->id() => auth()->user()->name];

        return view(config('cw_task.views') . 'create', $this->data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Request $request
     * @return \Illuminate\Response
     */
    public function store(StoreTaskRequest $request)
    {
        $task = resolve('TaskService')->create($request->all());
        return redirect()
            ->route('admin.tasks.edit', $task->id)
            ->with('status', 'tarefa criada com sucesso!');
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Response
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function show($id)
    {
        $task = resolve('TaskService')->find($id);
        abort_unless($task, 404);
        $this->authorize('update', $task);
        $this->data['task'] = $task;
        $this->data['task_format'] = $task->format();
        $this->data['breadcrumb'] = [
            route('admin.tasks.index') => 'Lista de tarefas',
            '#' => $this->data['task_format']['title']
        ];
        $this->data['buttons'] = [
            route('admin.tasks.index') => ['label' => __('return'), 'icon' => 'flaticon2-back'],
            route('admin.tasks.create') => ['label' => __('tasks.new'), 'icon' => 'fa fa-plus'],
            'include' => 'tasks.partials.buttons'
        ];
        $this->data['tasks'] = resolve('TaskService')->all();
        $this->data['types'] = resolve('TaskTypeService')->all();
        $this->data['employees'] = resolve('UserService')->employees();
        $this->data['associates'] = resolve('UserService')->associates();
        return view('tasks.show', $this->data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Response
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function edit(Request $request, $id)
    {
        $task = resolve('TaskService')->find($id);
        abort_unless($task, 404);
        $this->authorize('update', $task);
        $this->data['task'] = $task;
        $this->data['task_format'] = $task->format();
        $this->data['breadcrumb'] = [
            route('admin.tasks.index') => 'Lista de tarefas',
            '#' => $this->data['task_format']['title']
        ];
        $this->data['buttons'] = [
            route('admin.tasks.index') => ['label' => __('return'), 'icon' => 'flaticon2-back'],
            route('admin.tasks.create') => ['label' => __('tasks.new'), 'icon' => 'fa fa-plus'],
            route('admin.tasks.show', $task->id) => ['label' => __('tasks.show'), 'icon' => 'fa fa-show'],
        ];
        $this->data['statuses'] = Auth::user()->roleTasksStatuses;
        $taskemployees = [];//isset($request->taskemployees)? $request->taskemployees : $task->employees->pluck('id');
        $this->data['tasks'] = resolve('TaskService')->where(['employees', $taskemployees])->take(10)->get();
        $this->data['types'] = resolve('TaskTypeService')->all();
        $this->data['selecteds']['destinateds'] = isset($task) ? $task->destinateds->pluck('name', 'id') : [];
        $this->data['selecteds']['responsibles'] = isset($task) ? $task->responsibles->pluck('name', 'id') : [auth()->user()->name, auth()->id()];
        return view('tasks.edit', $this->data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Request $request
     * @param int $id
     * @return \Illuminate\Response
     */
    public function update(UpdateTaskRequest $request, $id)
    {
        $task = resolve('TaskService')->update($request->all(), $id);
        return redirect()
            ->route('admin.tasks.edit', $task->id)
            ->with('status', 'Tarefa editado com sucesso!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Response
     */
    public function destroy($id)
    {
        $task = resolve('TaskService')->destroy($id);
        return redirect()
            ->route('admin.tasks.index')
            ->with('status', 'Tarefa deletado com sucesso!');
    }

    /**
     * @param StoreTaskCommentRequest $request
     * @param $task_id
     * @return \Illuminate\RedirectResponse
     */
    public function storeComment(StoreTaskCommentRequest $request, $task_id)
    {
        $TaskService = resolve('TaskService')->createComment($request->all(), $task_id);
        return redirect()
            ->route('admin.tasks.show', $task_id)
            ->with('status', 'Comentário criado com sucesso!');
    }

    public function reschedule(Request $request, $id)
    {
        resolve('TaskService')->update($request->all(), $id);
        return back()->withInput()
            ->with('status', 'Tarefa atualizada com sucesso!');
    }

    public function updateStatus($id, $status_id)
    {
        $updateStatus = resolve('TaskService')->update(['status_id' => $status_id], $id);
        return back()->withInput()
            ->with('status', 'Status da tarefa atualizada com sucesso!');
    }

    public function updateDate(Request $request)
    {
        $data['datetime'] = gmdate('Y-m-d H:i:s', strtotime($request->date));
        return resolve('TaskService')->update($data, $request->task_id);
    }

    /**
     * @param $id - ID da tarefa
     * @return \Illuminate\RedirectResponse|string
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function close(Request $request, $id)
    {
        $task = resolve('TaskService')->find($id);
        abort_unless($task, 404);
        $this->authorize('update', $task);
        $comment = $request->comment;
        if (!isset($comment)) {
            return back()->withInput()
                ->with('danger', 'Para concluir a tarefa é necessário deixar um comentário!');
        }
        $close = resolve('TaskService')->close($id, $comment);
        //return back()->withInput()
        //->with('status', 'Tarefa atualizada com sucesso!');
        /*return redirect()
            ->route('admin.tasks.index')
            ->with('status', 'Tarefa atualizada com sucesso!');*/
        return redirect()->route('admin.tasks.index')->with('status', 'Tarefa atualizada com sucesso!');
    }

    public function datatable(Request $request)
    {
        $data = $request->all();

        $data['where'] = [];
        if (isset($data['search']['value'])) {
            $data['where']['name'] = $data['search']['value'];
            $data['orWhere']['contacts']['phone'] = $data['search']['value'];
            $data['orWhere']['contacts']['cellphone'] = $data['search']['value'];
            $data['orWhere']['roles'][] = $data['search']['value'];
        }

        if (isset($data['columns'][0]['search']['value'])) {
            $data['where']['types'] = explode(',', $data['columns'][0]['search']['value']);
        }

        if (isset($data['columns'][1]['search']['value'])) {
            $dataa = explode(',', $data['columns'][1]['search']['value']);
            $data['where']['from'] = isset($dataa[0])? $dataa[0] : NULL;
            $data['where']['to'] = isset($dataa[1])? $dataa[1] : NULL;
        }

        /*
        if (isset($data['columns'][3]['search']['value']) && $data['columns'][3]['search']['value'] > 0) {
            $data['where']['view_completed'] = 1;
        }
        */

        if (isset($data['columns'][6]['search']['value'])) {
            $data['where']['responsibles'] = explode(',', $data['columns'][6]['search']['value']);
        }

        $datatable = resolve('TaskService')->datatable($data);
        return (TaskResource::collection($datatable['data']))
            ->additional([
                'draw' => $datatable['draw'],
                'recordsTotal' => $datatable['recordsTotal'],
                'recordsFiltered' => $datatable['recordsFiltered']
            ]);
    }


}
