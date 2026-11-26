<x-app-layout>

    <div class="container">

        <div class="row">

            <div class="col-md-3">

                <h5 class="text-warning">Game Types</h5>

                @if($gameTypes->isEmpty())
                <span class="badge bg-secondary mb-2">
                    List Empty!, Add a Game Type
                </span>
                @endif

                <ul class="list-group" id="gameTypeList">

                    @foreach($gameTypes as $type)

                    <li class="list-group-item gameTypeBtn d-flex justify-content-between align-items-center"
                        data-id="{{$type->id}}"
                        style="cursor:pointer">

                        {{$type->name}}

                        <i class="bi bi-chevron-right text-muted"></i>

                    </li>

                    @endforeach

                </ul>

            </div>



            <div class="col-md-9">

                <h5>Payout Rules</h5>

                <button class="btn btn-success mb-2"
                    id="addRuleBtn">
                    Add Rule
                </button>


                <table class="table table-bordered">

                    <thead>

                        <tr>
                            <th>Bet Name</th>
                            <th>Position</th>
                            <th>Multiplier</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>

                    </thead>

                    <tbody id="rulesTable">

                    </tbody>

                </table>

            </div>

        </div>

    </div>


</x-app-layout>