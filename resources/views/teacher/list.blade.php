@extends('adminlte::page')

@section('content')


{{-- <div class="row">
    <table id="teacherList" class="dataTable">
        <thead>
            <tr>
                <th>Sl No</th>
                <th>Name</th>
                <th>Subject</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data as $item )
                <tr>
                    <td>1.</td>
                    <td>{{$item['fname']." ".$item['lname'] }}</td>
                    <td>Sub</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div> --}}
<div class="row">
    @foreach ($data as $item)
        <div class="col-sm-3">
             <div class="card card-primary card-outline">
        <div class="card-header">
            <h3 class="card-title">{{$item['fname']." ".$item['lname'] }}</h3>
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
            Subject: {{ $item['subject'] }}
        </div>
        <!-- /.card-body -->
        <div class="card-footer">
            <button type="button"  class="btn btn-block btn-outline-primary view-button" data-id="{{ $item['id'] }}">View Teacher</button>
        </div>
        <!-- /.card-footer -->
        </div>
    <!-- /.card -->
        </div>
    @endforeach
</div>






<!-- Modal -->
<div class="modal fade" id="viewDataModal" tabindex="-1" role="dialog" aria-labelledby="viewDataModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewDataModalLabel"></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">

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
            $(".view-button").on('click', function() {

                //write ajax code here
                //append the data inside the modal

                let id = $(this).data('id');
                $.ajax({
                    url:'/teachers/' + id,
                    type:'GET',
                    success: function(response) {
                        $('#viewDataModal .modal-title').text(response.fname + ' ' + response.lname)
                        $('#viewDataModal .modal-body').text('Subject: ' + response.subject)
                        $("#viewDataModal").modal('show');
                    },
                    error: function(xhr) {

                    }
                })


            })





        })

  $(function () {
    $("#teacherList").DataTable({
      "responsive": true, "lengthChange": false, "autoWidth": false,
      "buttons": ["copy", "csv", "excel", "pdf", "print", "colvis"]
    }).buttons().container().appendTo('#example1_wrapper .col-md-6:eq(0)');
    $('#example2').DataTable({
      "paging": true,
      "lengthChange": false,
      "searching": false,
      "ordering": true,
      "info": true,
      "autoWidth": false,
      "responsive": true,
    });
  });

    </script>
@stop
