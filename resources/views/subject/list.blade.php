@extends('adminlte::page')

@section('content')
<div class="row">
    @foreach ($data as $item)
        <div class="col-sm-3">
             <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title">{{$item['subject_name'] }}</h3>
            <div class="card-tools">
            <!-- Buttons, labels, and many other things can be placed here! -->
            <!-- Here is a label for example -->
            <span class="badge badge-primary">Active</span>
            <span class="badge badge-danger">Delete</span>
            </div>
            <!-- /.card-tools -->
        </div>
        <!-- /.card-header -->
        <div class="card-body">

        </div>
        <!-- /.card-body -->
        <div class="card-footer">
            <button type="button" class="btn btn-block btn-outline-primary option-button">Options</button>
        </div>
        <!-- /.card-footer -->
        </div>
    <!-- /.card -->
        </div>
    @endforeach
</div>





<!-- Modal -->
<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Modal title</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        ...
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary">Save changes</button>
      </div>
    </div>
  </div>
</div>


@stop

@section('js')
    <script>

$(document).ready(function() {
    $(".option-button").on('click', function() {
        $("#exampleModal").modal('show');
    })
})

    </script>
@stop
