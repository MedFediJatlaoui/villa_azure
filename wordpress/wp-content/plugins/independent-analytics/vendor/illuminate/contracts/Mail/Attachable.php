<?php

namespace IAWPSCOPED\Illuminate\Contracts\Mail;

/** @internal */
interface Attachable
{
    /**
     * Get an attachment instance for this entity.
     *
     * @return \Illuminate\Mail\Attachment
     */
    public function toMailAttachment();
}
