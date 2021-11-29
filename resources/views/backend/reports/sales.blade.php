@extends('backend.layouts.app')

@section('title', __('labels.backend.reports.sales_report').' | '.app_name())

@push('after-styles')
@endpush

@section('content')

    <div class="card">
        <div class="card-header">
            <h3 class="page-title d-inline">@lang('labels.backend.reports.sales_report')</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-lg-5">
                    <div class="card text-white bg-primary text-center">
                        <div class="card-body">
                            <h2 class="">{{$appCurrency['symbol'].' '.$total_earnings}}</h2>
                            <h5>@lang('labels.backend.reports.total_earnings')</h5>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-lg-5 ml-auto">
                    <div class="card text-white bg-success text-center">
                        <div class="card-body">
                            <h2 class="">{{$total_sales}}</h2>
                            <h5>@lang('labels.backend.reports.total_sales')</h5>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-12">
                    <h4>@lang('labels.backend.reports.courses')</h4>
                    <div class="table-responsive">
                        <table id="myCourseTable" class="table table-bordered table-striped ">
                            <thead>
                            <tr>
                                <th>@lang('labels.general.sr_no')</th>
                                <th>@lang('labels.backend.reports.fields.name')</th>
                                <th>@lang('labels.backend.reports.fields.orders')</th>
                                <th>@lang('labels.backend.reports.fields.earnings') <span style="font-weight: lighter">(in {{$appCurrency['symbol']}})</span></th>
                            </tr>
                            </thead>

                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <hr>
            <div class="row">
                <div class="col-12">
                    <h4>@lang('labels.backend.reports.bundles')</h4>
                    <div class="table-responsive">
                        <table id="myBundleTable" class="table table-bordered table-striped ">
                            <thead>
                            <tr>
                                <th>@lang('labels.general.sr_no')</th>
                                <th>@lang('labels.backend.reports.fields.name')</th>
                                <th>@lang('labels.backend.reports.fields.orders')</th>
                                <th>@lang('labels.backend.reports.fields.earnings') <span style="font-weight: lighter">(in {{$appCurrency['symbol']}})</span></th>
                            </tr>
                            </thead>

                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

@stop

@push('after-scripts')
    <script>

        $(document).ready(function () {
            var course_route = '{{route('admin.reports.get_course_data')}}';
            var bundle_route = '{{route('admin.reports.get_bundle_data')}}';

            $('#myCourseTable').DataTable({
                processing: true,
                serverSide: true,
                iDisplayLength: 10,
                retrieve: true,
                dom: 'lfBrtip<"actions">',
                buttons: [
                    {
                        extend: 'csv',
                        exportOptions: {
                            columns: ':visible',
                        }
                    },
                    {
                        extend: 'pdf',
                        exportOptions: {
                            columns: ':visible',
                        }
                    },
                    'colvis'
                ],
                ajax: course_route,
                columns: [
                    {data: "DT_RowIndex", name: 'DT_RowIndex', width: '8%'},
                    {data: "name", name: 'name'},
                    {data: "orders", name: 'orders'},
                    {data: "earnings", name: 'earnings'},
                ],


                createdRow: function (row, data, dataIndex) {
                    $(row).attr('data-entry-id', data.id);
                },
            });

            $('#myBundleTable').DataTable({
                processing: true,
                serverSide: true,
                iDisplayLength: 10,
                retrieve: true,
                dom: 'lfBrtip<"actions">',
                buttons: [
                    {
                        extend: 'csv',
                        exportOptions: {
                            columns: ':visible',
                        }
                    },
                    {
                        extend: 'pdf',
                        exportOptions: {
                            columns: ':visible',
                        }
                    },
                    'colvis'
                ],
                ajax: bundle_route,
                columns: [
                    {data: "DT_RowIndex", name: 'DT_RowIndex', width: '8%'},
                    {data: "name", name: 'name'},
                    {data: "orders", name: 'orders'},
                    {data: "earnings", name: 'earnings'},
                ],
                language:{
                    url : "//cdn.datatables.net/plug-ins/9dcbecd42ad/i18n/{{$locale_full_name}}.json",
                    buttons :{
                        colvis : '{{trans("datatable.colvis")}}',
                        pdf : '{{trans("datatable.pdf")}}',
                        csv : '{{trans("datatable.csv")}}',
                    }
                },


                createdRow: function (row, data, dataIndex) {
                    $(row).attr('data-entry-id', data.id);
                },
            });
        });

    </script>

@endpush