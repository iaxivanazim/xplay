<form id="chipForm">

    <input type="hidden" name="id" value="{{$chip->id ?? ''}}">


    <div class="row mb-3">
        <div class="col-md-12">
            <label class="form-label">Preset Name</label>
            <input type="text" name="preset_name" value="{{ $chip->preset_name ?? '' }}" class="form-control" required>
        </div>
    </div>

    <div class="row text-center">


        @for($i=1;$i<=5;$i++)

            <div class="col-md-2">

            <div class="casino-chip">

                <input type="number"
                    name="chip_{{$i}}_value"
                    value="{{$chip->{'chip_'.$i.'_value'} ?? 0}}"
                    class="chip-input">

            </div>

            <p class="mt-2">

                Chip {{$i}}

            </p>

    </div>

    @endfor


    </div>


    <div class="mt-4 text-center">

        <button class="btn btn-warning">

            Save Preset

        </button>

    </div>


</form>